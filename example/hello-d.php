#!/usr/bin/env php
<?php

// Enable Pathload API.
($GLOBALS['_PathLoad'] ?? require __DIR__ . '/dist/pathload.php');
// Add "./dist/" to the search-path. Bind "Example\\" to "extralib@1".
// Note there's a transitive dependency on 'corelib@1' which is handled automatically.
pathload()->addSearchDir(__DIR__ . '/dist')->addPackage('extralib@1', 'Example\\');

Example\ExtraLib::doStuff();
