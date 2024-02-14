<?php
namespace PathLoad\Vn;

class PathLoad implements \PathLoadInterface {

  /**
   * @var null|int
   */
  public $version;

  /**
   * List of globs that we will scan (if we need to load a package).
   *
   * Search-rules are evaluated lazily. Once evaluated, the data is merged into $availablePackages.
   * The rule is moved to $resolvedSearchRules.
   *
   * @var array
   *   Array([package => string, glob => string])
   * @internal
   */
  public $availableSearchRules = [];

  /**
   * List of globs that have been scanned already.
   *
   * @var array
   *   Array(string $glob => [package => string, glob => string])
   * @internal
   */
  public $resolvedSearchRules = [];

  /**
   * List of best-known versions for each package.
   *
   * Packages are loaded lazily. Once loaded, the data is moved to $resolvedPackages.
   *
   * @var array
   *   Array(string $majorName => [name => string, version => string, file => string type => string])
   * @internal
   */
  public $availablePackages = [];

  /**
   * List of package names that have already been resolved.
   *
   * @var array
   *   Array(string $majorName => [name => string, version => string, file => string type => string])
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

  /**
   * To load $package, you search files matching $glob.
   *
   * @param string $package
   *   Ex: 'cloud-io@1'
   *   Note: The special value '*' will be used to search for any package.
   * @param string $glob
   *   Ex: '/var/www/lib/*@*' or '/var/www/lib/cloud-io@1*.phar'
   * @return $this
   */
  public function addSearchRule(string $package, string $glob): \PathLoadInterface {
    if (!isset($this->resolvedSearchRules[$glob])) {
      $this->availableSearchRules[] = ['package' => $package, 'glob' => $glob];
    }
    return $this;
  }

  /**
   * Append a directory (with many packages) to the search-path.
   *
   * @param string $baseDir
   *   The path to a base directory (e.g. `/var/www/myapp/lib`) which contains many packages (e.g. `foo@1.2.3.phar` or `bar@4.5.6/autoload.php`).
   */
  public function addSearchDir(string $baseDir): \PathLoadInterface {
    return $this->addSearchRule('*', "$baseDir/*@*");
  }

  /**
   * Add a specific package.
   *
   * - By giving the `$namespaces`+`$package`, we can integrate with the autoloader - we will auto-load a package when the relevant namespace(s) are used.
   * - By giving the `$package`+`$baseDir`, we defer the need to `glob()` folders (until/unless someone actually needs $package).
   *
   * @param string $package
   *   Ex: ['DB_', 'GuzzleHttp\\']
   * @param string|array $namespaces
   *   Ex: 'foobar@1'
   * @param string|NULL $baseDir
   *   (EXPERIMENTAL) Add a search-rule just for this package. In theory, if used systemically, this would mean
   *   fewer calls to `glob()` for unused packages.
   *   Ex: '/var/www/myapp/lib'
   */
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
  public function addPackageNamespace(string $package, $namespaces): \PathLoadInterface {
    $namespaces = (array) $namespaces;
    foreach ($namespaces as $namespace) {
      $this->availableNamespaces[$namespace][$package] = $package;
    }
    return $this;
  }

  /**
   * Add a batch of information.
   *
   * @param array $all
   *   Ex: ['searchDirs' => [ ['/var/www/lib'], ['/usr/local/share/php'] ]]
   *   Ex: ['packages' => [ ['cloud-io@1', '] ]]
   * @param string $baseDir
   * @return \PathLoadInterface
   */
  public function import(array $all, string $baseDir = ''): \PathLoadInterface {
    foreach ($all['searchDirs'] ?? [] as $tuple) {
      $this->addSearchDir($this->withBaseDir($tuple[0], $baseDir));
    }
    foreach ($all['packages'] ?? [] as $tuple) {
      $this->addPackage($tuple[0], $tuple[1], isset($tuple[2]) ? $this->withBaseDir($tuple[2], $baseDir) : NULL);
    }
    foreach ($all['packageNamespaces'] ?? [] as $tuple) {
      $this->addPackageNamespace($tuple[0], $tuple[1]);
    }
    return $this;
  }

  /**
   * @param string|null $path
   * @param string|null $prefix
   * @return string
   *   If $path is absolute, then return that.
   *   If $path is relative, then prepend the $prefix.
   *
   */
  protected function withBaseDir(?string $path, ?string $prefix): string {
    if ($path === NULL || $prefix === NULL) {
      return $path;
    }
    if (DIRECTORY_SEPARATOR === '/' && $path[0] === DIRECTORY_SEPARATOR) {
      return $path;
    }
    if (DIRECTORY_SEPARATOR === '\\' && isset($path[1]) && $path[1] === ':') {
      return $path;
    }
    return $prefix . $path;
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
   * @param string $package
   *   Ex: 'cloud-io@1'
   * @return $this
   */
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

  /**
   * When loading a package, you may find metadata files
   * like "pathload.main.php" or "pathload.json". Load these.
   *
   * @param array $packageInfo
   *   Ex: ['name' => 'cloud-io', 'version' => '1.2.0', 'file' => 'cloud-io@1.2.0.phar']'
   * @param string $dir
   *   Ex: '/var/www/lib/cloud-io@1.2.0'
   *   Ex: 'phar:///var/www/lib/cloud-io@1.2.0.phar'
   */
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
   * @return array|null
   */
  protected function resolve(string $package): ?array {
    // if (strpos($package, '@') === FALSE) {}

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

  /**
   * Search a set of files. Examine the names to determine to available packages/versions.
   *
   * @param string $glob
   *   Ex: '/var/www/lib/*'
   */
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
        $this->availablePackages[$majorName] = [
          'name' => $name,
          'version' => $version,
          'file' => $file,
          'type' => $type,
        ];
      }
    }
  }

  /**
   * Split a package identifier into its parts.
   *
   * @param string $package
   *   Ex: ''foobar@1.2.3''
   * @return array
   *   Tuple: [$majorName, $name, $version]
   *   Ex: 'foobar@1', 'foobar', '1.2.3'
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
