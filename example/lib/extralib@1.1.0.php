<?php
namespace Example {
class ExtraLib {
  public static function doStuff() {
    CoreLib::greet();
    echo "and hello from extralib v1.0.0\n";
  }
}
}
