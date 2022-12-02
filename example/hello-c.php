#!/usr/bin/env php
<?php

// Enable Pathload API. Use fluent style.
($GLOBALS['_PathLoad'] ?? require __DIR__ . '/dist/pathload.php')
  // Add "./dist" to the search-path.
  ->append(__DIR__ . '/dist')
  // We specifically want to load 'extralib@1' right away.
  ->loadPackage('extralib@1');

Example\ExtraLib::doStuff();
