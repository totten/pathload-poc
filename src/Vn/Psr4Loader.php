<?php
namespace PathLoad\Vn;

class Psr4Loader {

  /**
   * @var array
   *   Ex: $prefixes['Foo\\'][0] = '/var/www/app/lib/foo@1.0.0/src/']
   * @internal
   */
  public $prefixes = [];

  public function addAll(string $dir, array $config) {
    foreach ($config as $prefix => $relPaths) {
      foreach ($relPaths as $relPath) {
        $this->addNamespace($prefix, $dir . '/' . $relPath);
      }
    }
  }

  /**
   * Adds a base directory for a namespace prefix.
   *
   * @param string $prefix
   *   The namespace prefix.
   * @param string $baseDir
   *   A base directory for class files in the namespace.
   * @return void
   */
  private function addNamespace($prefix, $baseDir) {
    $prefix = trim($prefix, '\\') . '\\';
    $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';
    if (isset($this->prefixes[$prefix]) === FALSE) {
      $this->prefixes[$prefix] = [];
    }
    array_push($this->prefixes[$prefix], $baseDir);
  }

  /**
   * Loads the class file for a given class name.
   *
   * @param string $class The fully-qualified class name.
   * @return mixed The mapped file name on success, or boolean false on failure.
   */
  public function loadClass(string $class) {
    $prefix = $class;

    while (FALSE !== $pos = strrpos($prefix, '\\')) {
      $prefix = substr($class, 0, $pos + 1);
      $relativeClass = substr($class, $pos + 1);
      if ($mappedFile = $this->findRelativeClass($prefix, $relativeClass)) {
        doRequire($mappedFile);
        return $mappedFile;
      }

      $prefix = rtrim($prefix, '\\');
    }

    return FALSE;
  }

  /**
   * Load the mapped file for a namespace prefix and relative class.
   *
   * @param string $prefix
   *   The namespace prefix.
   * @param string $relativeClass
   *   The relative class name.
   * @return string|FALSE
   *   Matched file name, or FALSE if none found.
   */
  private function findRelativeClass($prefix, $relativeClass) {
    if (isset($this->prefixes[$prefix]) === FALSE) {
      return FALSE;
    }
    $relFile = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    foreach ($this->prefixes[$prefix] as $baseDir) {
      $file = $baseDir . $relFile;
      if (file_exists($file)) {
        return $file;
      }
    }
    return FALSE;
  }

}
