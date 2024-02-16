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
   * Packages are loaded lazily. Once loaded, the data is moved to $resolvedPackages.
   *
   * @var Package[]
   *   Ex: ['cloud-file-io@1' => new Package('/usr/share/php-pathload/cloud-file-io@1.2.3.phar', ...)]
   * @internal
   */
  public $availablePackages = [];

  /**
   * List of packages that have already been resolved.
   *
   * @var Package[]
   *   Ex: ['cloud-file-io@1' => new Package('/usr/share/php-pathload/cloud-file-io@1.2.3.phar', ...)]
   *   Note: If PathLoad version is superceded, then the resolvedPackages may be instances of
   *   an old `Package` class. Be mindful of duck-type compatibility.
   *   We don't strictly need to retain this data, but it feels it'd be handy for debugging.
   * @internal
   */
  public $resolvedPackages = [];

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
   * @var Psr4Autoloader
   * @internal
   */
  public $psr4Classloader;

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

    // The exact protocol for assimilating $old instances may need change.
    // This seems like a fair guess as long as old properties are forward-compatible.

    if ($old === NULL) {
      $baseDirs = getenv('PHP_PATHLOAD') ? explode(PATH_SEPARATOR, getenv('PHP_PATHLOAD')) : [];
      foreach ($baseDirs as $baseDir) {
        $new->addSearchDir($baseDir);
      }
      $new->psr4Classloader = new Psr4Autoloader();
    }
    else {
      // TIP: You might use $old->version to decide what to use.
      foreach ($old->scanner->allRules as $rule) {
        $new->scanner->addRule($rule);
      }
      $new->resolvedPackages = $old->resolvedPackages;
      $new->availableNamespaces = $old->availableNamespaces;
      $new->psr4Classloader = $old->psr4Classloader;
    }

    $new->register();
    return new Versions($new);
  }

  /**
   * Append a directory (with many packages) to the search-path.
   *
   * @param string $baseDir
   *   The path to a base directory (e.g. `/var/www/myapp/lib`) which contains many packages (e.g. `foo@1.2.3.phar` or `bar@4.5.6/autoload.php`).
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

  /**
   * Register the autoloader.
   */
  public function register(): \PathLoadInterface {
    spl_autoload_register([$this, 'loadClass']);
    return $this;
  }

  /**
   * Un-register the autoloader.
   */
  public function unregister(): \PathLoadInterface {
    spl_autoload_unregister([$this, 'loadClass']);
    return $this;
  }

  /**
   * @param string $class
   * @return mixed
   * @see \spl_autoload_register()
   */
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
   * If the application requests class "Foo\Bar\Whiz\Bang", then you should load
   * any packages related to "Foo\*", "Foo\Bar\*", or "Foo\Bar\Whiz\*".
   *
   * @param string $delim
   *   Ex: '\\' or '_'
   * @param string[] $classParts
   *   Ex: ['Symfony', 'Components', 'Filesystem', 'Filesystem']
   */
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
          unset($this->availableNamespaces[$namespace]); /* Don't revisit these $packages in the future. */
          foreach ($packages as $package) {
            $this->loadPackage($package);
          }
        }
      }
    } while ($foundPackages);
    // Loading a package could produce metadata about other packages.
    // Assimilate those too.
  }

  /**
   * Load the content of a package.
   *
   * @param string $majorName
   *   Ex: 'cloud-io@1'
   * @return $this
   */
  public function loadPackage(string $majorName): PathLoad {
    if (isset($this->resolvedPackages[$majorName])) {
      return $this;
    }
    $package = $this->resolve($majorName);
    switch ($package->type ?? NULL) {
      case 'php':
        doRequire($package->file);
        return $this;

      case 'phar':
        doRequire($package->file);
        $this->useMetadataFiles($package, 'phar://' . $package->file);
        return $this;

      case 'dir':
        $this->useMetadataFiles($package, $package->file);
        return $this;

      default:
        error_log("Failed to load package \"$majorName\".");
        return $this;
    }
  }

  /**
   * When loading a package, you may find metadata files
   * like "pathload.main.php" or "pathload.json". Load these.
   *
   * @param Package $package
   * @param string $dir
   *   Ex: '/var/www/lib/cloud-io@1.2.0'
   *   Ex: 'phar:///var/www/lib/cloud-io@1.2.0.phar'
   */
  protected function useMetadataFiles(Package $package, string $dir): void {
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

  /**
   * @param string $name
   *   Ex: 'cloud-io@1'
   *   Ex: 'cloud-io@1.2.3'
   * @param string|null $dir
   *   Used for applying the 'autoload' rules.
   *   Ex: '/var/www/lib/cloud-io@1.2.3'
   * @param array $config
   *   Ex: ['autoload' => ['psr4' => ...], 'require-namespace' => [...], 'require-package' => [...]]
   *
   * @return \PathLoadInterface
   */
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

  /**
   * @param string $package
   *   Ex: 'cloud-io@1'
   * @return Package|null
   */
  protected function resolve(string $package): ?Package {
    //internal// if (strpos($package, '@') === FALSE) { error... }

    [$majorName, $name] = Package::parseExpr($package);
    if (isset($this->resolvedPackages[$majorName])) {
      return $this->resolvedPackages[$majorName];
    }

    foreach ($this->scanner->scan($name) as $packageRec) {
      /** @var Package $packageRec */
      if (!isset($this->availablePackages[$packageRec->majorName]) || version_compare($packageRec->version, $this->availablePackages[$packageRec->majorName]->version, '>')) {
        $this->availablePackages[$packageRec->majorName] = $packageRec;
      }
    }

    if (isset($this->availablePackages[$majorName])) {
      $this->resolvedPackages[$majorName] = $this->availablePackages[$majorName];
      unset($this->availablePackages[$majorName]);
      return $this->resolvedPackages[$majorName];
    }

    error_log("PathLoad: Failed to resolve \"$package\"");
    return NULL;
  }

}
