#!/usr/bin/env php
<?php

// Enable Pathload API.
($GLOBALS['_PathLoad'][0] ?? require dirname(__DIR__) . '/dist/pathload-0.php');
// Add "./lib/" to the search-path. Bind "Example\\" to "extralib@1".
// Note there's a transitive dependency on 'corelib@1' which is handled automatically.
pathload()->addSearchDir(__DIR__ . '/lib')->addPackage('extralib@1', 'Example\\');

Example\ExtraLib::doStuff();
