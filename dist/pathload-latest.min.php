<?php
namespace {
  if (isset($GLOBALS['_PathLoad'][0])) {
    return $GLOBALS['_PathLoad'][0];
  }
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
     * (Pathload v0) Enable autoloading of a specific `*.phar`, `*.php`, or folder.
     * Useful for non-standard file-layout.
     *
     * @method PathLoadInterface addSearchItem(string $name, string $version, string $file, ?string $type = NULL)
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
     * @method PathLoadInterface loadPackage(string $majorName)
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
    class Versions implements \ArrayAccess {
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
    class Package {
      public static function parseExpr(string $package): array {
        if (strpos($package, '@') === FALSE) {
          throw new \RuntimeException("Malformed package name: $package");
        }
        [$prefix, $suffix] = explode('@', $package, 2);
        $prefix = str_replace('/', '~', $prefix);
        [$major] = explode('.', $suffix, 2);
        return ["$prefix@$major", $prefix, $suffix];
      }
      public static function parseFileType(string $file): array {
        if (substr($file, -4) === '.php') {
          return ['php', substr(basename($file), 0, -4)];
        }
        elseif (substr($file, '-5') === '.phar') {
          return ['phar', substr(basename($file), 0, -5)];
        }
        elseif (is_dir($file)) {
          return ['dir', basename($file)];
        }
        else {
          return [NULL, NULL];
        }
      }
      public static function create(string $file): ?Package {
        [$type, $base] = self::parseFileType($file);
        if ($type === NULL) {
          return NULL;
        }
        $self = new Package();
        [$self->majorName, $self->name, $self->version] = static::parseExpr($base);
        $self->file = $file;
        $self->type = $type;
        return $self;
      }
      public $file;
      public $name;
      public $majorName;
      public $version;
      public $type;
    }
    class Scanner {
      public $allRules = [];
      public $newRules = [];
      public function addRule(array $rule): void {
            $id = static::id($rule);
        $this->allRules[$id] = $rule;
        $this->newRules[$id] = $rule;
      }
      public function reset(): void {
        $this->newRules = $this->allRules;
        $this->oldRules = [];
      }
      public function scan(string $packageHint): \Generator {
        yield from [];
        foreach (array_keys($this->newRules) as $id) {
          $searchRule = $this->newRules[$id];
          if ($searchRule['package'] === '*' || $searchRule['package'] === $packageHint) {
                    unset($this->newRules[$id]);
            if (isset($searchRule['glob'])) {
              foreach ((array) glob($searchRule['glob']) as $file) {
                if (($package = Package::create($file)) !== NULL) {
                  yield $package;
                }
              }
            }
            if (isset($searchRule['file'])) {
              $package = new Package();
              $package->file = $searchRule['file'];
              $package->name = $searchRule['package'];
              $package->majorName = $searchRule['package'] . '@' . explode('.', $searchRule['version'])[0];
              $package->version = $searchRule['version'];
              $package->type = $searchRule['type'] ?: Package::parseFileType($searchRule['file'])[0];
              yield $package;
            }
          }
        }
      }
      protected static function id(array $searchRule): string {
        if (isset($searchRule['glob'])) {
          return $searchRule['glob'];
        }
        elseif (isset($searchRule['file'])) {
          return md5(implode(' ', [$searchRule['file'], $searchRule['package'], $searchRule['version']]));
        }
        else {
          throw new \RuntimeException("Cannot identify rule: " . json_encode($searchRule));
        }
      }
    }
    class ClassLoader {
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
    class PathLoad implements \PathLoadInterface {
      public $version;
      public $scanner;
      public $availablePackages = [];
      public $loadedPackages = [];
      public $availableNamespaces;
      public $classLoader;
      public static function create(int $version, ?\PathLoadInterface $old = NULL) {
        if ($old !== NULL) {
          $old->unregister();
        }
        $new = new static();
        $new->version = $version;
        $new->scanner = new Scanner();
        if ($old === NULL) {
          $baseDirs = getenv('PHP_PATHLOAD') ? explode(PATH_SEPARATOR, getenv('PHP_PATHLOAD')) : [];
          foreach ($baseDirs as $baseDir) {
            $new->addSearchDir($baseDir);
          }
          $new->classLoader = new ClassLoader();
        }
        else {
                foreach ($old->scanner->allRules as $rule) {
            $new->scanner->addRule($rule);
          }
          $new->loadedPackages = $old->loadedPackages;
          $new->availableNamespaces = $old->availableNamespaces;
          $new->classLoader = $old->classLoader;
        }
        $new->register();
        return new Versions($new);
      }
      public function addSearchDir(string $baseDir): \PathLoadInterface {
        $this->scanner->addRule(['package' => '*', 'glob' => "$baseDir/*@*"]);
        return $this;
      }
      public function addSearchItem(string $name, string $version, string $file, ?string $type = NULL): \PathLoadInterface {
        $this->scanner->addRule(['package' => $name, 'version' => $version, 'file' => $file, 'type' => $type]);
        return $this;
      }
      public function addPackage(string $package, $namespaces): \PathLoadInterface {
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
        return $this->classLoader->loadClass($class);
      }
      private function loadPackagesByNamespace(string $delim, array $classParts): void {
        array_pop($classParts);
        do {
          $foundPackages = FALSE;
          $namespace = '';
          foreach ($classParts as $nsPart) {
            $namespace .= $nsPart . $delim;
            if (isset($this->availableNamespaces[$namespace])) {
              $packages = $this->availableNamespaces[$namespace];
              foreach ($packages as $package) {
                unset($this->availableNamespaces[$namespace][$package]);
                if ($this->loadPackage($package)) {
                  $foundPackages = TRUE;
                }
                else {
                  trigger_error("PathLoad: Failed to locate package \"$package\" required for namespace \"$namespace\"", E_USER_WARNING);
                  $this->availableNamespaces[$namespace][$package] = $package;
                }
              }
            }
          }
        } while ($foundPackages);
              }
      public function loadPackage(string $majorName): ?string {
        if (isset($this->loadedPackages[$majorName])) {
          return $this->loadedPackages[$majorName]->version;
        }
        $this->scanAvailablePackages($majorName);
        if (!isset($this->availablePackages[$majorName])) {
          return NULL;
        }
        $package = $this->loadedPackages[$majorName] = $this->availablePackages[$majorName];
        unset($this->availablePackages[$majorName]);
        switch ($package->type ?? NULL) {
          case 'php':
            doRequire($package->file);
            return $package->version;
          case 'phar':
            doRequire($package->file);
            $this->useMetadataFiles($package, 'phar://' . $package->file);
            return $package->version;
          case 'dir':
            $this->useMetadataFiles($package, $package->file);
            return $package->version;
          default:
            \error_log("PathLoad: Package (\"$majorName\") appears malformed.");
            return NULL;
        }
      }
      private function scanAvailablePackages(string $majorName): void {
        [, $name] = Package::parseExpr($majorName);
        foreach ($this->scanner->scan($name) as $packageRec) {
          if (!isset($this->availablePackages[$packageRec->majorName]) || \version_compare($packageRec->version, $this->availablePackages[$packageRec->majorName]->version, '>')) {
            $this->availablePackages[$packageRec->majorName] = $packageRec;
          }
        }
      }
      private function useMetadataFiles(Package $package, string $dir): void {
        $phpFile = "$dir/pathload.main.php";
        $jsonFile = "$dir/pathload.json";
        if (file_exists($phpFile)) {
          require $phpFile;
        }
        elseif (file_exists($jsonFile)) {
          $jsonData = json_decode(file_get_contents($jsonFile), TRUE);
          $packageId = $package->name . '@' . $package->version;
          $this->activatePackage($packageId, $dir, $jsonData);
        }
      }
      public function activatePackage(string $name, ?string $dir, array $config): \PathLoadInterface {
        if (isset($config['autoload'])) {
          if ($dir === NULL) {
            throw new \RuntimeException("Cannot activate package $name. The 'autoload' property requires a base-directory.");
          }
          $this->classLoader->addAutoloadJson($dir, $config['autoload']);
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
    }
  }
}

namespace {
  // New or upgraded instance.
  $GLOBALS['_PathLoad'] = \PathLoad\V0\PathLoad::create(0, $GLOBALS['_PathLoad']['top'] ?? NULL);
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
