<?php
namespace PathLoad\Vn;

class Psr0Loader {

  /**
   * @var array
   *  Ex: $paths['F']['Foo_'][0] = '/var/www/app/lib/foo@1.0.0/src/';
   * @internal
   */
  public $paths = [];

  /**
   * @param string $dir
   * @param array $config
   *   Ex: ['Foo_' => ['src/']] or ['Foo_' => ['Foo_']]
   */
  public function addAll(string $dir, array $config) {
    $dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    foreach ($config as $prefix => $relPaths) {
      $bucket = $prefix[0];
      foreach ((array) $relPaths as $relPath) {
        $this->paths[$bucket][$prefix][] = $dir . $relPath;
      }
    }
  }

  /**
   * Loads the class file for a given class name.
   *
   * @param string $class The fully-qualified class name.
   * @return mixed The mapped file name on success, or boolean false on failure.
   */
  public function loadClass(string $class) {
    $bucket = $class[0];
    if (!isset($this->paths[$bucket])) {
      return FALSE;
    }

    $file = DIRECTORY_SEPARATOR . str_replace(['_', '\\'], [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $class) . '.php';
    foreach ($this->paths[$bucket] as $prefix => $paths) {
      if ($prefix === substr($class, 0, strlen($prefix))) {
        foreach ($paths as $path) {
          $fullFile = $path . $file;
          if (file_exists($fullFile)) {
            doRequire($fullFile);
            return $fullFile;
          }
        }
      }
    }

    return FALSE;
  }

}
