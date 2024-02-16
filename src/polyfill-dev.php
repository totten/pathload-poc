<?php

namespace {
  if (!defined('PATHLOAD_VERSION')) {
    define('PATHLOAD_VERSION', require __DIR__ . '/version.php');
  }

  if (isset($GLOBALS['_PathLoad'][PATHLOAD_VERSION])) {
    return $GLOBALS['_PathLoad'][PATHLOAD_VERSION];
  }

  if (!interface_exists('PathLoadInterface')) {
    require_once __DIR__ . '/PathLoadInterface.php';
  }

  if (!class_exists('\PathLoad\Vn\PathLoad')) {
    require_once __DIR__ . '/Vn/funcs.php';
    require_once __DIR__ . '/Vn/Versions.php';
    require_once __DIR__ . '/Vn/Package.php';
    require_once __DIR__ . '/Vn/Scanner.php';
    require_once __DIR__ . '/Vn/PathLoad.php';
    require_once __DIR__ . '/Vn/Psr4Autoloader.php';
  }

  // New or upgraded instance.
  $GLOBALS['_PathLoad'] = \PathLoad\Vn\PathLoad::create(PATHLOAD_VERSION, $GLOBALS['_PathLoad']['top'] ?? NULL);

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
