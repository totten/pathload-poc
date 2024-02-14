<?php

namespace {
  if (!interface_exists('PathLoadInterface')) {
    /**
     * The PathLoad interface is defined via soft signatures ("duck-typing") rather than hard signatures.
     * This matters when multiple parties inject PathLoad support onto a pre-existing framework.
     * In the event of future language changes or contract changes, the soft signatures
     * give wiggle-room to address interoperability/conversion.
     *
     * ==== PACKAGE CONSUMER APIS ===
     *
     * (PathLoad v0) Enable autoloading of `*.phar`, `*.php`, and folders from a search directory.
     *
     * @method PathLoadInterface addSearchDir(string $baseDir)
     *
     * (PathLoad v0) Declare knowledge about what packages are available. These provide
     * hints for autoloading.
     *
     * The third argument, `$baseDir`, is experimental
     *
     * @method PathLoadInterface addPackage(string $package, $namespaces, ?string $baseDir = NULL)
     *
     * (Pathload v0) When you need resources from a package, call loadPackage().
     * This locates the relevant files and loads them.
     * If you use namespace-autoloading, then this shouldn't be necessary.
     *
     * @method PathLoadInterface loadPackage(string $package)
     *
     * ==== PACKAGE PROVIDER APIS ====
     *
     * (PathLoad v0) Activate your package. This allows you to add metadata about activating
     * your own package. In particular, this may be necessary if you have transitive
     * dependencies. This would be appropriate for single-file PHP package (`cloud-io@1.0.0.php`)
     * which lack direct support for `pathload.json`.
     *
     * @method PathLoadInterface activatePackage(string $package, string $dir, array $config)
     */
    interface PathLoadInterface {
    }
  }
}

namespace PathLoad\V0 {
  if (!class_exists('PathLoad')) {
    function doRequire(string $file) {
      return require $file;
    }
    class PathLoadVersions implements \ArrayAccess {
      public $top;
      public function __construct($top) {
        $this->top = $top;
      }
      public function offsetExists($version): bool {
        return ($version === 'top' || $version <= $this->top->version);
      }
        public function offsetGet($version) {
        if ($version === 'top' || $version <= $this->top->version) {
          return $this->top;
        }
        return NULL;
      }
      public function offsetSet($offset, $value): void {
        error_log("Cannot overwrite PathLoad[$offset]");
      }
      public function offsetUnset($offset): void {
        error_log("Cannot remove PathLoad[$offset]");
      }
    }
    class PathLoad implements \PathLoadInterface {
      public $version;
      public $availableSearchRules = [];
      public $resolvedSearchRules = [];
      public $availablePackages = [];
      public $resolvedPackages = [];
      public $availableNamespaces;
      public $psr4Classloader;
      public static function create(int $version, ?\PathLoadInterface $old = NULL) {
        if ($old !== NULL) {
          $old->unregister();
        }
        $new = new static();
        $new->version = $version;
        if ($old === NULL) {
          $baseDirs = getenv('PHP_PATHLOAD') ? explode(PATH_SEPARATOR, getenv('PHP_PATHLOAD')) : [];
          foreach ($baseDirs as $baseDir) {
            $new->addSearchDir($baseDir);
          }
          $new->psr4Classloader = new Psr4Autoloader();
        }
        else {
                $new->availableSearchRules = $old->availableSearchRules;
          $new->resolvedSearchRules = $old->resolvedSearchRules;
          $new->availablePackages = $old->availablePackages;
          $new->resolvedPackages = $old->resolvedPackages;
          $new->availableNamespaces = $old->availableNamespaces;
          $new->psr4Classloader = $old->psr4Classloader;
        }
        $new->register();
        return new PathLoadVersions($new);
      }
      public function addSearchRule(string $package, string $glob): \PathLoadInterface {
        if (!isset($this->resolvedSearchRules[$glob])) {
          $this->availableSearchRules[] = ['package' => $package, 'glob' => $glob];
        }
        return $this;
      }
      public function addSearchDir(string $baseDir): \PathLoadInterface {
        return $this->addSearchRule('*', "$baseDir/*@*");
      }
      public function addPackage(string $package, $namespaces, ?string $baseDir = NULL): \PathLoadInterface {
        $this->addPackageNamespace($package, $namespaces);
        if ($baseDir) {
          $glob = strpos($package, '@') === FALSE
            ? "{$baseDir}/{$package}@*"
            : "{$baseDir}/{$package}*";
          $this->addSearchRule($package, $glob);
        }
        return $this;
      }
      private function addPackageNamespace(string $package, $namespaces): \PathLoadInterface {
        $namespaces = (array) $namespaces;
        foreach ($namespaces as $namespace) {
          $this->availableNamespaces[$namespace][$package] = $package;
        }
        return $this;
      }
      public function register(): \PathLoadInterface {
        spl_autoload_register([$this, 'loadClass']);
        return $this;
      }
      public function unregister(): \PathLoadInterface {
        spl_autoload_unregister([$this, 'loadClass']);
        return $this;
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
      protected function loadPackagesByNamespace(string $delim, array $classParts): void {
        array_pop($classParts);
        do {
          $foundPackages = FALSE;
          $namespace = '';
          foreach ($classParts as $nsPart) {
            $namespace .= $nsPart . $delim;
            if (isset($this->availableNamespaces[$namespace])) {
              $foundPackages = TRUE;
              $packages = $this->availableNamespaces[$namespace];
              unset($this->availableNamespaces[$namespace]);
              foreach ($packages as $package) {
                $this->loadPackage($package);
              }
            }
          }
        } while ($foundPackages);
              }
      public function loadPackage(string $package): PathLoad {
        if (isset($this->resolvedPackages[$package])) {
          return $this;
        }
        $packageInfo = $this->resolve($package);
        switch ($packageInfo['type'] ?? NULL) {
          case 'php':
            doRequire($packageInfo['file']);
            return $this;
          case 'phar':
            doRequire($packageInfo['file']);
            $this->useMetadataFiles($packageInfo, 'phar://' . $packageInfo['file']);
            return $this;
          case 'dir':
            $this->useMetadataFiles($packageInfo, $packageInfo['file']);
            return $this;
          default:
            error_log("Failed to load package \"$package\".");
            return $this;
        }
      }
      protected function useMetadataFiles(array $packageInfo, string $dir): void {
        $bootFile = "$dir/pathload.main.php";
        if (file_exists($bootFile)) {
          require $bootFile;
        }
        $pathloadJsonFile = "$dir/pathload.json";
        if (file_exists($pathloadJsonFile)) {
          $pathloadJsonData = file_get_contents($pathloadJsonFile);
          $pathLoadJson = json_decode($pathloadJsonData, TRUE);
          $packageId = $packageInfo['name'] . '@' . $packageInfo['version'];
          $this->activatePackage($packageId, $dir, $pathLoadJson);
        }
      }
      public function activatePackage(string $name, ?string $dir, array $config): \PathLoadInterface {
        if (isset($config['autoload'])) {
          if ($dir === NULL) {
            throw new \RuntimeException("Cannot activate package $name. The 'autoload' property requires a base-directory.");
          }
          $this->psr4Classloader->addAutoloadJson($dir, $config['autoload']);
          foreach ($config['require-namespace'] ?? [] as $nsRule) {
            foreach ((array) $nsRule['package'] as $package) {
              foreach ((array) $nsRule['prefix'] as $prefix) {
                $this->availableNamespaces[$prefix][$package] = $package;
              }
            }
          }
          foreach ($config['require-package'] ?? [] as $package) {
            $this->loadPackage($package);
          }
        }
        return $this;
      }
      protected function resolve(string $package): ?array {
        [$majorName] = static::parsePackage($package);
        if (isset($this->resolvedPackages[$majorName])) {
          return $this->resolvedPackages[$majorName];
        }
        foreach (array_keys($this->availableSearchRules) as $key) {
          $searchRule = $this->availableSearchRules[$key];
          if ($searchRule['package'] === '*' || $searchRule['package'] === $majorName) {
            $this->resolvedSearchRules[$searchRule['glob']] = $searchRule;
            unset($this->availableSearchRules[$key]);
            $this->scan($searchRule['glob']);
          }
        }
        if (isset($this->availablePackages[$majorName])) {
          $this->resolvedPackages[$majorName] = $this->availablePackages[$majorName];
          unset($this->availablePackages[$majorName]);
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
                    continue;
          }
          if (!isset($this->availablePackages[$majorName]) || version_compare($this->availablePackages[$majorName]['version'], $version, '<')) {
            $this->availablePackages[$majorName] = [
              'name' => $name,
              'version' => $version,
              'file' => $file,
              'type' => $type,
            ];
          }
        }
      }
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
    class Psr4Autoloader {
      public $prefixes = [];
      public function addAutoloadJson(string $dir, array $autoloadJson) {
        if (!empty($autoloadJson['include'])) {
                      foreach ($autoloadJson['include'] as $file) {
            $this->requireFile($dir . '/' . $file);
          }
        }
        foreach ($autoloadJson['psr-4'] ?? [] as $prefix => $relPaths) {
          foreach ($relPaths as $relPath) {
            $this->addNamespace($prefix, $dir . '/' . $relPath);
          }
        }
        foreach ($autoloadJson['psr-0'] ?? [] as $prefix => $relPath) {
          error_log("TODO: Load psr-0 data from $dir ($prefix => $relPath");
              }
      }
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
  if (!isset($GLOBALS['_PathLoad'][0])) {
    $GLOBALS['_PathLoad'] = \PathLoad\V0\PathLoad::create(0, $GLOBALS['_PathLoad']['top'] ?? NULL);
  }
  if (!function_exists('pathload')) {
    /**
     * Get a reference the PathLoad manager.
     *
     * @param int|string $version
     * @return \PathLoadInterface
     */
    function pathload($version = 'top') {
      return $GLOBALS['_PathLoad'][$version];
    }
  }
  return pathload();
}
