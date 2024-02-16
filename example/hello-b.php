#!/usr/bin/env php
<?php

// Enable Pathload API. Use fluent style.
($GLOBALS['_PathLoad'][0] ?? require dirname(__DIR__) . '/dist/pathload-0.php')
  // Add "./lib" to the search-path.
  ->addSearchDir(__DIR__ . '/lib')
  // Bind a namespace to a package. If "Example\*" classes are accessed, then load "corelib@1" and "extralib@1".
  ->addPackage('corelib@1', 'Example\\')
  ->addPackage('extralib@1', 'Example\\');

Example\ExtraLib::doStuff();
