#!/usr/bin/env php
<?php

// Enable Pathload API. Use fluent style.
($GLOBALS['_PathLoad'] ?? require __DIR__ . '/../src/pathload.php')
  // Add "./dist" to the search-path.
  ->append(__DIR__ . '/dist')
  // Bind a namespace to a package. If "Example\*" classes are accessed, then load "corelib@1" and "extralib@1".
  ->addPackage('Example\\', 'corelib@1')
  ->addPackage('Example\\', 'extralib@1');

Example\ExtraLib::doStuff();
