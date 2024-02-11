<?php

namespace PathLoad {
  if (!class_exists('PathLoad')) {
    function doRequire(string $file) {
      return require $file;
    }
    class PathLoad {
      protected $searchRules = [];
      protected $scanned = [];
      protected $availablePackages = [];
      protected $resolvedPackages = [];
      protected $namespaces;
      protected $psr4Classloader;
      public function __construct(array $baseDirs = []) {
        $this->psr4Classloader = new Psr4Autoloader();
        foreach ($baseDirs as $baseDir) {
          $this->append($baseDir);
        }
      }
      public function append(string $baseDir): PathLoad {
        $this->searchRules[] = ['package' => '*', 'glob' => "$baseDir/*@*"];
        return $this;
      }
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
              unset($this->namespaces[$namespace]);
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
                      }
        }
      }
      protected function resolve(string $package): ?array {
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
                        continue;
          }
          if (!isset($this->availablePackages[$majorName]) || version_compare($this->availablePackages[$majorName]['version'], $version, '<')) {
            $this->availablePackages[$majorName] = ['name' => $name, 'version' => $version, 'file' => $file, 'type' => $type];
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
      protected $prefixes = [];
      public function register() {
        spl_autoload_register([$this, 'loadClass']);
      }
      public function unregister() {
        spl_autoload_unregister([$this, 'loadClass']);
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
