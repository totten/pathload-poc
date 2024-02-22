<?php

namespace PathLoad\Vn;

/**
 * Locate version-compliant instances of PathLoad.
 */
class Versions implements \ArrayAccess {

  //INTERNAL//  $x[0] ==> PathLoad instance compatible with v0
  //INTERNAL//  $x[1] ==> PathLoad instance compatible with v1
  //INTERNAL//  $x[12] ==> PathLoad instance compatible with v12
  //INTERNAL//  $x['top'] ==> Whatever version is latest/current
  //INTERNAL//  $x->top ==> Whatever version is latest/current

  public $top;

  public function __construct($top) {
    $this->top = $top;
  }

  public function offsetExists($version): bool {
    return ($version === 'top' || $version <= $this->top->version);
  }

  public function offsetGet($version): ?\PathLoadInterface {
    if ($version === 'top' || $version <= $this->top->version) {
      return $this->top;
    }
    return NULL;
  }

  public function offsetSet($offset, $value): void {
    error_log("Cannot overwrite PathLoad[$offset]");
  }

  public function offsetUnset($offset): void {
    error_log("Cannot remove PathLoad[$offset]");
  }

}
