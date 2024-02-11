<?php
namespace PathLoad;

class PathLoad implements PathLoadInterface {

  public $version = '0.1';

  /**
   * List of globs that we will scan (if we need to load a package).
   *
   * Search-rules are evaluated lazily. Once evaluated, the data is merged into $availablePackages.
   * The rule is moved to $resolvedSearchRules.
   *
   * @var array
   *   Array([package => string, glob => string])
   */
  public $availableSearchRules = [];

  /**
   * List of globs that have been scanned already.
   *
   * @var array
   *   Array(string $glob => [package => string, glob => string])
   */
  public $resolvedSearchRules = [];

  /**
   * List of best-known versions for each package.
   *
   * Packages are loaded lazily. Once loaded, the data is moved to $resolvedPackages.
   *
   * @var array
   *   Array(string $majorName => [name => string, version => string, file => string type => string])
   */
  public $availablePackages = [];

  /**
   * List of package names that have already been resolved.
   *
   * @var array
   *   Array(string $majorName => [name => string, version => string, file => string type => string])
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
   */
  public $availableNamespaces;

  /**
   * @var Psr4Autoloader
   */
  public $psr4Classloader;

  public function __construct(array $baseDirs = []) {
    $this->psr4Classloader = new Psr4Autoloader();
    foreach ($baseDirs as $baseDir) {
      $this->addSearchDir($baseDir);
    }
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
  public function addSearchRule(string $package, string $glob): PathLoadInterface {
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
  public function addSearchDir(string $baseDir): PathLoadInterface {
    return $this->addSearchRule('*', "$baseDir/*@*");
  }

  /**
   * Add a specific package. This is similar to `append()` but requires hints -- which allow better behavior:
   *
   * - By giving the `$namespaces`+`$package`, we can integrate with the autoloader - we will auto-load a package when the relevant namespace(s) are used.
   * - By giving the `$package`+`$baseDir`, we defer the need to `glob()` folders (until/unless someone actually needs $package).
   *
   * @param string $package
   *   Ex: ['DB_', 'GuzzleHttp\\']
   * @param string|array $namespaces
   *   Ex: 'foobar@1'
   * @param string|NULL $baseDir
   *   Ex: '/var/www/myapp/lib'
   */
  public function addPackage(string $package, $namespaces, ?string $baseDir = NULL): PathLoadInterface {
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
  public function addPackageNamespace(string $package, $namespaces): PathLoadInterface {
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
   * @return \PathLoad\PathLoadInterface
   */
  public function addAll(array $all, string $baseDir = ''): PathLoadInterface {
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
   * If $path is relative, then add a prefix to it.
   *
   * @param string|null $path
   * @param string|null $prefix
   * @return string
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
  public function register(): PathLoadInterface {
    spl_autoload_register([$this, 'loadClass']);
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

  /**
   * When loading a package, you may find metadata files
   * like "pathload.php" or "composer.json". Load these.
   *
   * @param string $dir
   *   Ex: '/var/www/lib/cloud-io@1.2.0'
   *   Ex: 'phar:///var/www/lib/cloud-io@1.2.0.phar'
   */
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
