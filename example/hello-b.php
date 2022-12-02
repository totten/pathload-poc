#!/usr/bin/env php
<?php

($GLOBALS['_PathLoad'] ?? require __DIR__ . '/../src/pathload.php')
  ->append(__DIR__ . '/dist')
  ->addPackage('Example\\', 'corelib@1')
  ->addPackage('Example\\', 'extralib@1');

Example\ExtraLib::doStuff();
