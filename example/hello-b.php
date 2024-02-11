#!/usr/bin/env php
<?php

// Enable Pathload API. Use fluent style.
($GLOBALS['_PathLoad'][0] ?? require __DIR__ . '/dist/pathload.php')
  // Add "./dist" to the search-path.
  ->addSearchDir(__DIR__ . '/dist')
  // Bind a namespace to a package. If "Example\*" classes are accessed, then load "corelib@1" and "extralib@1".
  ->addPackage('corelib@1', 'Example\\')
  ->addPackage('extralib@1', 'Example\\');

Example\ExtraLib::doStuff();
