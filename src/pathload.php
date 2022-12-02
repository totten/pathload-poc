<?php

namespace PathLoad {
  if (!class_exists('PathLoad')) {
    require_once __DIR__ . '/psr4.php';

    class PathLoad {

      /**
       * List of globs that we will scan (if we need to load a package).
       *
       * @var array([package => string, glob => string])
       */
      protected $searchRules = [];

      /**
       * List of globs that have been scanned already.
       *
       * @var array(string $glob => bool)
       */
      protected $scanned = [];

      /**
       * List of best-known versions for each package.
       *
       * @var array(string $majorName => array(string $version, string $baseDir)))
       */
      protected $availablePackages = [];

      /**
       * List of package names that have already been resolved.
       *
       * @var array(string $majorName => bool)
       */
      protected $resolvedPackages = [];

      /**
       * List of hints for class-loading. If someone tries to use a matching class, then
       * load the corresponding package.
       *
       * @var array(string $prefix => array $packages)
       */
      protected $namespaces;

      /**
       * @var \Psr4Autoloader
       */
      protected $psr4Classloader;

      public function __construct(array $baseDirs = []) {
        $this->psr4Classloader = new \Psr4Autoloader();
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

      // Day-to-day loaders
      // public function include(string $name) {
      //   return doInclude($this->resolve($name));
      // }
      //
      // public function include_once(string $name) {
      //   return doIncludeOnce($this->resolve($name));
      // }
      //
      // public function require(string $name) {
      //   return doRequire($this->resolve($name));
      // }
      //
      // public function require_once(string $name) {
      //   return doRequireOnce($this->resolve($name));
      // }

      // Path resolution

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

