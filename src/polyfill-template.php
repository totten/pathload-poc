<?php

namespace {
  if (isset($GLOBALS['_PathLoad'][PATHLOAD_VERSION])) {
    return $GLOBALS['_PathLoad'][PATHLOAD_VERSION];
  }

  if (!interface_exists('PathLoadInterface')) {
    //INCLUDE:PathLoadInterface//
  }
}

namespace PATHLOAD_NS {
  if (!class_exists('PathLoad')) {
    //INCLUDE:funcs//
    //INCLUDE:Versions//
    //INCLUDE:Package//
    //INCLUDE:Scanner//
    //INCLUDE:ClassLoader//
    //INCLUDE:PathLoad//
  }
}

namespace {
  // New or upgraded instance.
  $GLOBALS['_PathLoad'] = \PATHLOAD_NS\PathLoad::create(PATHLOAD_VERSION, $GLOBALS['_PathLoad']['top'] ?? NULL);

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
