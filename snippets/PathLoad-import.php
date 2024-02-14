<?php

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
