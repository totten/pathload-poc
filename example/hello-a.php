#!/usr/bin/env php
<?php

// Enable Pathload API
($GLOBALS['_PathLoad'] ?? require __DIR__ . '/../src/pathload.php');

// Bind a namespace to a package. If "Example\*" classes are used, then "corelib@1" will be loaded.
pathload()->addPackage('Example\\', 'corelib@1', __DIR__ . '/dist');

// Use some classes
Example\CoreLib::greet();
