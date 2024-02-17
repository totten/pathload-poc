<?php
pathload()->activatePackage('reloadable@1', __DIR__, [
  'reloadable' => TRUE,
]);

global $Reloadable;

$Reloadable = new class {

  public function greet(string $name): void {
    printf("Hello %s from reloadable v1.0.0\n", $name);
  }

};
