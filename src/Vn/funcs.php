<?php
namespace PathLoad\Vn;

function doRequire(string $file) {
  //INTERNAL// Scope-restricted
  require_once $file;
}
