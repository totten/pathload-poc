<?php
namespace Reloadable {
pathload()->activatePackage('reloadable@1', __DIR__, [
  'autoload' => [], /* PSR-0 and PSR-4 are not reloadable patterns */
  'reloadable' => TRUE,
]);

global $Reloadable;

$Reloadable = new class {

  public function greet(string $name): void {
    printf("Hello %s from reloadable v1.3.0\n", $name);
  }

};
}
