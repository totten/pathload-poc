<?php

pathload()->activatePackage('retrolib@1', __DIR__, [
  'autoload' => [
    'psr-0' => [
      'RetroScore_' => ['score/'],
      'RetroSlash\\' => 'slash/',
    ],
  ]
]);