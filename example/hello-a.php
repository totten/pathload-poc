#!/usr/bin/env php
<?php

// Enable Pathload API
($GLOBALS['_PathLoad'][0] ?? require __DIR__ . '/dist/pathload.php');

// Bind a namespace to a package. If "Example\*" classes are used, then "corelib@1" will be loaded.
pathload()->addPackage('corelib@1', 'Example\\', __DIR__ . '/dist');

// Use some classes
Example\CoreLib::greet();
