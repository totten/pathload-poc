<?php

namespace PathLoad {
  if (!class_exists('PathLoad')) {
    //CLASSES//
  }
}

namespace {
  if (!isset($GLOBALS['_PathLoad'])) {
    $GLOBALS['_PathLoad'] = new \PathLoad\PathLoad(
      getenv('PHP_PATHLOAD') ? explode(PATH_SEPARATOR, getenv('PHP_PATHLOAD')) : []
    );
    $GLOBALS['_PathLoad']->register();
  }

  function pathload(): \PathLoad\PathLoadInterface {
    return $GLOBALS['_PathLoad'];
  }

  return pathload();
}
