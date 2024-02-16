<?php

namespace PathLoad\Test;

/**
 * Construct a path relative to the src tree.
 * @param string|null $subPath
 * @return string
 */
function srcPath(?string $subPath = NULL): string {
  $path = dirname(__DIR__);
  if (!is_dir($path)) {
    mkdir($path);
  }

  if ($subPath !== NULL) {
    $path .= '/' . $subPath;
  }

  return $path;
}

function currentPolyfill() {
  return srcPath('dist/pathload-latest.php');
  // return srcPath('src/polyfill-dev.php');
}

/**
 * Build a directory with a set of libraries.
 *
 * @param string $name
 * @param array $libs
 *   Ex: ['corelib@1.2.0' => 'phar']
 *   Ex: ['corelib@1.2.0' => 'dir']
 * @return string
 *   The path to the generated directory
 */
function buildLibDir(string $name, array $libs): string {
  $path = srcPath('tmp/' . $name);

  if (is_dir($path)) {
    deleteDir($path);
  }
  mkdir($path, 0777, TRUE);

  foreach ($libs as $libId => $libType) {
    switch ($libType) {
      case 'phar':
      case 'php':
        $from = srcPath("example/dist/$libId.$libType");
        $to = "$path/$libId.$libType";
        symlink($from, $to);
        break;

      case 'dir':
        $from = srcPath("example/lib/$libId");
        $to = "$path/$libId";
        symlink($from, $to);
        break;

      default:
        throw new \RuntimeException("Unrecognized library ($libId => $libType)");
    }
  }

  return $path;
}

function deleteDir($path): bool {
  if (!is_dir($path)) {
    return FALSE;
  }

  $files = array_diff(scandir($path), array('.', '..'));
  foreach ($files as $file) {
    $filePath = $path . DIRECTORY_SEPARATOR . $file;

    if (is_link($filePath)) {
      unlink($filePath);
    }
    elseif (is_dir($filePath)) {
      deleteDir($filePath);
    }
    else {
      unlink($filePath);
    }
  }

  return rmdir($path);
}

require_once srcPath('src/PathLoadTestCase.php');
