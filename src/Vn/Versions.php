<?php

namespace PathLoad\Vn;

/**
 * A facade for returning version-compliant flavors of PathLoad.
 *
 * $x[0] ==> PathLoad instance compatible with v0
 * $x[1] ==> PathLoad instance compatible with v1
 * $x[12] ==> PathLoad instance compatible with v12
 * $x['top'] ==> Whatever version is latest/current
 * $x->top ==> Whatever version is latest/current
 */
class Versions implements \ArrayAccess {

  public $top;

  public function __construct($top) {
    $this->top = $top;
  }

  public function offsetExists($version): bool {
    return ($version === 'top' || $version <= $this->top->version);
  }

  #[\ReturnTypeWillChange]
  public function offsetGet($version) {
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
