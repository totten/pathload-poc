<?php

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
