#!/usr/bin/env php
<?php

// Enable Pathload API
($GLOBALS['_PathLoad'][0] ?? require dirname(__DIR__) . '/dist/pathload-0.php');

pathload()
  // Add "./lib" to the search-path.
  ->addSearchDir(__DIR__ . '/lib')
  // Bind a namespace to a package. If "Example\*" classes are accessed, then load "corelib@1" and "extralib@1".
  ->addNamespace('corelib@1', 'Example\\');

// Use some classes
Example\CoreLib::greet();
