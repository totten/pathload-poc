<?php

namespace PathLoad {
  if (!class_exists('PathLoad')) {
    class PathLoad {

      /**
       * List of globs that we will scan (if we need to load a package).
       *
       * @var array
       *   Array([package => string, glob => string])
       */
      protected $searchRules = [];

      /**
       * List of globs that have been scanned already.
       *
       * @var array
       *   Array(string $glob => bool)
       */
      protected $scanned = [];

      /**
       * List of best-known versions for each package.
       *
       * @var array
       *   Array(string $majorName => array(string $version, string $baseDir)))
       */
      protected $availablePackages = [];

      /**
       * List of package names that have already been resolved.
       *
       * @var array
       *   Array(string $majorName => bool)
       */
      protected $resolvedPackages = [];

      /**
       * List of hints for class-loading. If someone tries to use a matching class, then
       * load the corresponding package.
       *
       * @var array
       *   Array(string $prefix => array $packages)
       */
      protected $namespaces;

      /**
       * @var Psr4Autoloader
       */
      protected $psr4Classloader;

      public function __construct(array $baseDirs = []) {
        $this->psr4Classloader = new Psr4Autoloader();
        foreach ($baseDirs as $baseDir) {
          $this->append($baseDir);
        }
      }

      /**
       * Append a directory (with many packages) to the search-path.
       *
       * @param string $baseDir
       *   The path to a base directory (e.g. `/var/www/myapp/lib`) which contains many packages (e.g. `foo@1.2.3.phar` or `bar@4.5.6/autoload.php`).
       */
      public function append(string $baseDir): PathLoad {
        $this->searchRules[] = ['package' => '*', 'glob' => "$baseDir/*@*"];
        return $this;
      }

      /**
       * Add a specific package. This is similar to `append()` but requires hints -- which allow better behavior:
       *
       * - By giving the `$package`, we defer the need to `glob()` folders (until/unless someone actually needs $package).
       * - By giving the `$namespaces`, we can integrate the autoloader - so you can use classes without any extra `requireOnce()` calls.
       *
       * @param string|array $namespaces
       *   Ex: ['DB_', 'GuzzleHttp\\']
       * @param string $package
       *   Ex: 'foobar@1'
       * @param string|NULL $baseDir
       *   Ex: '/var/www/myapp/lib'
       */
      public function addPackage($namespaces, string $package, ?string $baseDir = NULL): PathLoad {
        $namespaces = (array) $namespaces;
        foreach ($namespaces as $namespace) {
          $this->namespaces[$namespace][] = $package;
        }

        if ($baseDir) {
          $glob = strpos($package, '@') === FALSE
            ? "{$baseDir}/{$package}@*"
            : "{$baseDir}/{$package}*";
          if (!isset($this->scanned[$glob])) {
            $this->searchRules[] = ['package' => $package, 'glob' => $glob];
          }
        }

        return $this;
      }

      public function register() {
        spl_autoload_register([$this, 'loadClass']);
      }

      public function loadClass(string $class) {
        if (strpos($class, '\\') !== FALSE) {
          $this->loadPackagesByNamespace('\\', explode('\\', $class));
        }
        elseif (strpos($class, '_') !== FALSE) {
          $this->loadPackagesByNamespace('_', explode('_', $class));
        }

        return $this->psr4Classloader->loadClass($class);
      }

      /**
       * @param string $delim
       *   Ex: '\\' or '_'
       * @param string[] $classParts
       *   Ex: ['Symfony', 'Components', 'Filesystem', 'Filesystem]
       */
      protected function loadPackagesByNamespace(string $delim, array $classParts): void {
        array_pop($classParts);
        do {
          $foundPackages = FALSE;
          $namespace = '';
          foreach ($classParts as $nsPart) {
            $namespace .= $nsPart . $delim;
            if (isset($this->namespaces[$namespace])) {
              $foundPackages = TRUE;
              $packages = $this->namespaces[$namespace];
              unset($this->namespaces[$namespace]); /* Don't revisit these $packages in the future. */
              foreach ($packages as $package) {
                $this->loadPackage($package);
              }
            }
          }
        } while ($foundPackages);
      }

      public function loadPackage(string $package): PathLoad {
        $packageInfo = $this->resolve($package);
        switch ($packageInfo['type'] ?? NULL) {
          case 'php':
            doRequire($packageInfo['file']);
            return $this;

          case 'phar':
            doRequire($packageInfo['file']);
            $this->useMetadataFiles('phar://' . $packageInfo['file']);
            return $this;

          case 'dir':
            $this->useMetadataFiles($packageInfo['file']);
            return $this;

          default:
            error_log("Failed to load package \"$package\".");
            return $this;
        }
      }

      protected function useMetadataFiles(string $dir): void {
        $bootFile = "$dir/.config/pathload.php";
        if ($bootFile) {
          require $bootFile;
        }

        $composerJsonFile = "$dir/composer.json";
        if (file_exists($composerJsonFile)) {
          $composerJsonData = file_get_contents($composerJsonFile);
          $compserJson = \json_decode($composerJsonData, TRUE);
          if (!empty($compserJson['autoload']['include'])) {
            // Would it be better to just warn? We can't really do the same semantics, but this
            // arguably might help in some cases.
            foreach ($compserJson['autoload']['include'] as $file) {
              doRequire($dir . '/' . $file);
            }
          }
          foreach ($compserJson['autoload']['psr-4'] ?? [] as $prefix => $relPaths) {
            foreach ($relPaths as $relPath) {
              $this->psr4Classloader->addNamespace($prefix, $dir . '/' . $relPath);
            }
          }
          foreach ($compserJson['autoload']['psr-0'] ?? [] as $prefix => $relPath) {
            error_log("TODO: Load psr-0 data from $composerJsonFile ($prefix => $relPath");
            // $this->psr4Classloader->addNamespace($prefix, $relPath);
          }
        }
      }

      protected function resolve(string $package): ?array {
        // if (strpos($package, '@') === FALSE) {}

        [$majorName] = static::parsePackage($package);
        if (isset($this->resolvedPackages[$majorName])) {
          return $this->resolvedPackages[$majorName];
        }

        foreach (array_keys($this->searchRules) as $key) {
          $searchRule = $this->searchRules[$key];
          if ($searchRule['package'] === '*' || $searchRule['package'] === $majorName) {
            unset($this->searchRules[$key]);
            $this->scan($searchRule['glob']);
          }
        }

        if (isset($this->availablePackages[$majorName])) {
          $this->resolvedPackages[$majorName] = $this->availablePackages[$majorName];
          return $this->resolvedPackages[$majorName];
        }

        error_log("Failed to resolve \"$package\"");
        return NULL;
      }

      protected function scan(string $glob): void {
        foreach ((array) glob($glob) as $file) {
          if (substr($file, -4) === '.php') {
            [$majorName, $name, $version] = static::parsePackage(substr(basename($file), 0, -4));
            $type = 'php';
          }
          elseif (substr($file, '-5') === '.phar') {
            [$majorName, $name, $version] = static::parsePackage(substr(basename($file), 0, -5));
            $type = 'phar';
          }
          elseif (is_dir($file)) {
            [$majorName, $name, $version] = static::parsePackage(basename($file));
            $type = 'dir';
          }
          else {
            // Not for us.
            continue;
          }

          if (!isset($this->availablePackages[$majorName]) || version_compare($this->availablePackages[$majorName]['version'], $version, '<')) {
            $this->availablePackages[$majorName] = ['name' => $name, 'version' => $version, 'file' => $file, 'type' => $type];
          }
        }
      }

      /**
       * @param string $package
       * @return array
       *   Ex: [$majorName, $name, $version] = parsePackage('foobar@1.2.3');
       */
      protected static function parsePackage(string $package): array {
        if (strpos($package, '@') === FALSE) {
          throw new \RuntimeException("Malformed package name: $package");
        }
        [$prefix, $suffix] = explode('@', $package, 2);
        $prefix = str_replace('/', '~', $prefix);
        [$major] = explode('.', $suffix, 2);
        return ["$prefix@$major", $prefix, $suffix];
      }

    }

    function doRequire(string $file) {
      return require $file;
    }

    class Psr4Autoloader {

      protected $prefixes = [];

      public function register() {
        spl_autoload_register([$this, 'loadClass']);
      }

      /**
       * Unregister loader with SPL autoloader stack.
       *
       * @return void
       */
      public function unregister() {
        spl_autoload_unregister([$this, 'loadClass']);
      }

      /**
       * Adds a base directory for a namespace prefix.
       *
       * @param string $prefix The namespace prefix.
       * @param string $base_dir A base directory for class files in the
       * namespace.
       * @param bool $prepend If true, prepend the base directory to the stack
       * instead of appending it; this causes it to be searched first rather
       * than last.
       *
       * @return void
       */
      public function addNamespace($prefix, $base_dir, $prepend = FALSE) {
        $prefix = trim($prefix, '\\') . '\\';
        $base_dir = rtrim($base_dir, DIRECTORY_SEPARATOR) . '/';
        if (isset($this->prefixes[$prefix]) === FALSE) {
          $this->prefixes[$prefix] = [];
        }

        if ($prepend) {
          array_unshift($this->prefixes[$prefix], $base_dir);
        }
        else {
          array_push($this->prefixes[$prefix], $base_dir);
        }
      }

      /**
       * Loads the class file for a given class name.
       *
       * @param string $class The fully-qualified class name.
       * @return mixed The mapped file name on success, or boolean false on failure.
       */
      public function loadClass($class) {
        $prefix = $class;

        while (FALSE !== $pos = strrpos($prefix, '\\')) {
          $prefix = substr($class, 0, $pos + 1);
          $relative_class = substr($class, $pos + 1);
          $mapped_file = $this->loadMappedFile($prefix, $relative_class);
          if ($mapped_file) {
            return $mapped_file;
          }

          $prefix = rtrim($prefix, '\\');
        }

        return FALSE;
      }

      /**
       * Load the mapped file for a namespace prefix and relative class.
       *
       * @param string $prefix The namespace prefix.
       * @param string $relative_class The relative class name.
       * @return mixed
       *   Boolean false if no mapped file can be loaded, or the
       *   name of the mapped file that was loaded.
       */
      protected function loadMappedFile($prefix, $relative_class) {
        if (isset($this->prefixes[$prefix]) === FALSE) {
          return FALSE;
        }
        foreach ($this->prefixes[$prefix] as $base_dir) {
          $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
          if ($this->requireFile($file)) {
            return $file;
          }
        }
        return FALSE;
      }

      /**
       * If a file exists, require it from the file system.
       *
       * @param string $file The file to require.
       * @return bool True if the file exists, false if not.
       */
      protected function requireFile($file) {
        if (file_exists($file)) {
          require $file;
          return TRUE;
        }
        return FALSE;
      }

    }
  }
}

namespace {
  if (!isset($GLOBALS['_PathLoad'])) {
    $GLOBALS['_PathLoad'] = new \PathLoad\PathLoad(
      getenv('PHP_PATHLOAD') ? explode(PATH_SEPARATOR, getenv('PHP_PATHLOAD')) : []
    );
    $GLOBALS['_PathLoad']->register();
  }

  function pathload(): \PathLoad\PathLoad {
    return $GLOBALS['_PathLoad'];
  }

  return pathload();
}
