#!/usr/bin/env php
<?php

// Enable Pathload API
($GLOBALS['_PathLoad'][0] ?? require __DIR__ . '/dist/pathload.php');

pathload()
  // Add "./dist" to the search-path.
  ->addSearchDir(__DIR__ . '/dist')
  // Bind a namespace to a package. If "Example\*" classes are accessed, then load "corelib@1" and "extralib@1".
  ->addPackage('corelib@1', 'Example\\');

// Use some classes
Example\CoreLib::greet();
