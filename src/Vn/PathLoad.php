<?php
namespace PathLoad\Vn;

class PathLoad implements \PathLoadInterface {

  /**
   * @var null|int
   */
  public $version;

  /**
   * @var Scanner
   * @internal
   */
  public $scanner;

  /**
   * List of best-known versions for each package.
   *
   * Packages are loaded lazily. Once loaded, the data is moved to $loadedPackages.
   *
   * @var Package[]
   *   Ex: ['cloud-file-io@1' => new Package('/usr/share/php-pathload/cloud-file-io@1.2.3.phar',
   *   ...)]
   * @internal
   */
  public $availablePackages = [];

  /**
   * List of packages that have already been resolved.
   *
   * @var Package[]
   *   Ex: ['cloud-file-io@1' => new Package('/usr/share/php-pathload/cloud-file-io@1.2.3.phar',
   *   ...)] Note: If PathLoad version is super-ceded, then the loadedPackages may be instances of
   *   an old `Package` class. Be mindful of duck-type compatibility. We don't strictly need to
   *   retain this data, but it feels it'd be handy for debugging.
   * @internal
   */
  public $loadedPackages = [];

  /**
   * Log of package activations. Used to re-initialize class-loader if we upgrade.
   *
   * @var array
   * @internal
   */
  public $activatedPackages = [];

  /**
   * List of hints for class-loading. If someone tries to use a matching class, then
   * load the corresponding package.
   *
   * Namespace-rules are evaluated lazily. Once evaluated, the data is removed.
   *
   * @var array
   *   Array(string $prefix => [string $package => string $package])
   *   Ex: ['Super\Cloud\IO\' => ['cloud-io@1' => 'cloud-io@1']
   * @internal
   */
  public $availableNamespaces;

  /**
   * @var \PathLoad\Vn\Psr0Loader
   * @internal
   */
  public $psr0;

  /**
   * @var \PathLoad\Vn\Psr4Loader
   * @internal
   */
  public $psr4;

  /**
   * @param int $version
   *   Identify the version being instantiated.
   * @param \PathLoadInterface|null $old
   *   If this instance is a replacement for an older instance, then it will be passed in.
   * @return \ArrayAccess
   *   Versioned work-a-like array.
   */
  public static function create(int $version, ?\PathLoadInterface $old = NULL) {
    if ($old !== NULL) {
      $old->unregister();
    }

    $new = new static();
    $new->version = $version;
    $new->scanner = new Scanner();
    $new->psr0 = new Psr0Loader();
    $new->psr4 = new Psr4Loader();
    $new->register();

    // The exact protocol for assimilating $old instances may need change.
    // This seems like a fair guess as long as old properties are forward-compatible.

    if ($old === NULL) {
      $baseDirs = getenv('PHP_PATHLOAD') ? explode(PATH_SEPARATOR, getenv('PHP_PATHLOAD')) : [];
      foreach ($baseDirs as $baseDir) {
        $new->addSearchDir($baseDir);
      }
    }
    else {
      // TIP: You might use $old->version to decide what to use.
      foreach ($old->scanner->allRules as $rule) {
        $new->scanner->addRule($rule);
      }
      $new->loadedPackages = $old->loadedPackages;
      $new->availableNamespaces = $old->availableNamespaces;
      foreach ($old->activatedPackages as $activatedPackage) {
        $new->activatePackage($activatedPackage['name'], $activatedPackage['dir'], $activatedPackage['config']);
      }
    }

    return new Versions($new);
  }

  public function register(): \PathLoadInterface {
    spl_autoload_register([$this, 'loadClass']);
    return $this;
  }

  public function unregister(): \PathLoadInterface {
    spl_autoload_unregister([$this, 'loadClass']);
    return $this;
  }

  public function reset(): \PathLoadInterface {
    $this->scanner->reset();
    return $this;
  }

  /**
   * Append a directory (with many packages) to the search-path.
   *
   * @param string $baseDir
   *   The path to a base directory (e.g. `/var/www/myapp/lib`) which contains many packages (e.g.
   *   `foo@1.2.3.phar` or `bar@4.5.6/autoload.php`).
   */
  public function addSearchDir(string $baseDir): \PathLoadInterface {
    $this->scanner->addRule(['package' => '*', 'glob' => "$baseDir/*@*"]);
    return $this;
  }

  /**
   * Append one specific item to the search list.
   *
   * @param string $name
   *   Ex: 'cloud-file-io'
   * @param string $version
   *   Ex: '1.2.3'
   * @param string $file
   *   Full path to the file or folder.
   * @param string|null $type
   *   One of: 'php', 'phar', or 'dir'. NULL will auto-detect.
   *
   * @return \PathLoadInterface
   */
  public function addSearchItem(string $name, string $version, string $file, ?string $type = NULL): \PathLoadInterface {
    $this->scanner->addRule(['package' => $name, 'version' => $version, 'file' => $file, 'type' => $type]);
    return $this;
  }

  /**
   * Declare that a $package includes some list of namespaces.
   *
   * If someone requests a class in $namespace, then we load $package.
   *
   * @param string $package
   *   Ex: 'cloud-io@1'
   * @param string|string[] $namespaces
   *   Ex: 'Super\Cloud\IO\'
   */
  public function addPackage(string $package, $namespaces): \PathLoadInterface {
    $namespaces = (array) $namespaces;
    foreach ($namespaces as $namespace) {
      $this->availableNamespaces[$namespace][$package] = $package;
    }
    return $this;
  }

  public function loadClass(string $class) {
    if (strpos($class, '\\') !== FALSE) {
      $this->loadPackagesByNamespace('\\', explode('\\', $class));
    }
    elseif (strpos($class, '_') !== FALSE) {
      $this->loadPackagesByNamespace('_', explode('_', $class));
    }

    return $this->psr4->loadClass($class) || $this->psr0->loadClass($class);
  }

  /**
   * If the application requests class "Foo\Bar\Whiz\Bang", then you should load
   * any packages related to "Foo\*", "Foo\Bar\*", or "Foo\Bar\Whiz\*".
   *
   * @param string $delim
   *   Ex: '\\' or '_'
   * @param string[] $classParts
   *   Ex: ['Symfony', 'Components', 'Filesystem', 'Filesystem']
   */
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
              $this->availableNamespaces[$namespace][$package] = $package; /* Maybe some other time */
            }
          }
        }
      }
    } while ($foundPackages);
    // Loading a package could produce metadata about other packages. Assimilate those too.
  }

  /**
   * Load the content of a package.
   *
   * @param string $majorName
   *   Ex: 'cloud-io@1'
   * @return string|NULL
   *   The version# of the loaded package. Otherwise, NULL
   */
  public function loadPackage(string $majorName, bool $reload = FALSE): ?string {
    if (isset($this->loadedPackages[$majorName])) {
      if ($reload && $this->loadedPackages[$majorName]->reloadable) {
        $this->scanner->reset();
      }
      else {
        return $this->loadedPackages[$majorName]->version;
      }
    }

    $this->scanAvailablePackages(explode('@', $majorName, 2)[0], $this->availablePackages);
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

  private function scanAvailablePackages(string $hint, array &$avail): void {
    foreach ($this->scanner->scan($hint) as $package) {
      /** @var Package $package */
      if (!isset($avail[$package->majorName]) || \version_compare($package->version, $avail[$package->majorName]->version, '>')) {
        $avail[$package->majorName] = $package;
      }
    }
  }

  /**
   * When loading a package, execute metadata files like "pathload.main.php" or "pathload.json".
   *
   * @param Package $package
   * @param string $dir
   *   Ex: '/var/www/lib/cloud-io@1.2.0'
   *   Ex: 'phar:///var/www/lib/cloud-io@1.2.0.phar'
   */
  private function useMetadataFiles(Package $package, string $dir): void {
    $phpFile = "$dir/pathload.main.php";
    $jsonFile = "$dir/pathload.json";

    if (file_exists($phpFile)) {
      require $phpFile;
    }
    elseif (file_exists($jsonFile)) {
      $jsonData = json_decode(file_get_contents($jsonFile), TRUE);
      $id = $package->name . '@' . $package->version;
      $this->activatePackage($id, $dir, $jsonData);
    }
  }

  /**
   * Given a configuration for the package, activate the correspond autoloader rules.
   *
   * @param string $majorName
   *   Ex: 'cloud-io@1'
   * @param string|null $dir
   *   Used for applying the 'autoload' rules.
   *   Ex: '/var/www/lib/cloud-io@1.2.3'
   * @param array $config
   *   Ex: ['autoload' => ['psr4' => ...], 'require-namespace' => [...], 'require-package' => [...]]
   * @return \PathLoadInterface
   */
  public function activatePackage(string $majorName, ?string $dir, array $config): \PathLoadInterface {
    if (isset($config['reloadable'])) {
      $this->loadedPackages[$majorName]->reloadable = $config['reloadable'];
    }
    if (!isset($config['autoload'])) {
      return $this;
    }
    if ($dir === NULL) {
      throw new \RuntimeException("Cannot activate package $majorName. The 'autoload' property requires a base-directory.");
    }

    $this->activatedPackages[] = ['name' => $majorName, 'dir' => $dir, 'config' => $config];

    if (!empty($config['autoload']['include'])) {
      foreach ($config['autoload']['include'] as $file) {
        doRequire($dir . DIRECTORY_SEPARATOR . $file);
      }
    }
    if (isset($config['autoload']['psr-0'])) {
      $this->psr0->addAll($dir, $config['autoload']['psr-0']);
    }
    if (isset($config['autoload']['psr-4'])) {
      $this->psr4->addAll($dir, $config['autoload']['psr-4']);
    }

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
    return $this;
  }

}
