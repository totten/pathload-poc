<?php

// Classes in 'extralib' depend on packages from 'corelib@1'.
pathload()->activatePackage('extralib@1' , __DIR__, [
  'autoload' => [
    'psr-4' => [
      'Example\\' => ['src/'],
    ]
  ],
  'require-namespace' => [
    ['package' => 'corelib@1', 'prefix' => 'Example\\'],
  ]
]);

__HALT_COMPILER();
