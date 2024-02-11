<?php

namespace {
  if (!interface_exists('PathLoadInterface')) {
    //INCLUDE:PathLoadInterface//
  }
}

namespace PATHLOAD_NS {
  if (!class_exists('PathLoad')) {
    //INCLUDE:funcs//
    //INCLUDE:PathLoad//
    //INCLUDE:Psr4Autoloader//
  }
}

namespace {
  if (!isset($GLOBALS['_PathLoad']['top']) || $GLOBALS['_PathLoad']['top']->version < PATHLOAD_VERSION) {
    $GLOBALS['_PathLoad'] = \PATHLOAD_NS\PathLoad::create(PATHLOAD_VERSION, $GLOBALS['_PathLoad']['top'] ?? NULL);
  }

  if (!function_exists('pathload')) {

    function pathload(): \PathLoadInterface {
      return $GLOBALS['_PathLoad']['top'];
    }

  }

  return pathload();
}
