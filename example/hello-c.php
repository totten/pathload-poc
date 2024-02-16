#!/usr/bin/env php
<?php

// Enable Pathload API. Use fluent style.
($GLOBALS['_PathLoad'][0] ?? require dirname(__DIR__) . '/dist/pathload-0.php')
  // Add "./lib" to the search-path.
  ->addSearchDir(__DIR__ . '/lib')
  // We specifically want to load 'extralib@1' right away.
  ->loadPackage('extralib@1');

Example\ExtraLib::doStuff();
