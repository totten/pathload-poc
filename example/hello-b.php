#!/usr/bin/env php
<?php

printf("[Run %s]\n", __FILE__);

($GLOBALS['_PathLoad'] ?? require __DIR__ . '/../src/pathload.php')
  ->append(__DIR__ . '/dist')
  ->addPackage('Example\\', 'corelib@1')
  ->addPackage('Example\\', 'extralib@1');

Example\ExtraLib::doStuff();
