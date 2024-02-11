<?php

namespace {
  if (!interface_exists('PathLoadInterface')) {
    //INCLUDE:PathLoadInterface//
  }
}

namespace PATHLOAD_NS {
  if (!class_exists('PathLoad')) {
    //INCLUDE:funcs//
    //INCLUDE:PathLoadVersions//
    //INCLUDE:PathLoad//
    //INCLUDE:Psr4Autoloader//
  }
}

namespace {
  if (!isset($GLOBALS['_PathLoad'][PATHLOAD_VERSION])) {
    $GLOBALS['_PathLoad'] = \PATHLOAD_NS\PathLoad::create(PATHLOAD_VERSION, $GLOBALS['_PathLoad']['top'] ?? NULL);
  }

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
