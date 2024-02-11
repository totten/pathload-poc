#!/usr/bin/env php
<?php

// Enable Pathload API.
($GLOBALS['_PathLoad'] ?? require __DIR__ . '/dist/pathload.php');
// Add "./lib/" to the search-path. Bind "Example\\" to "extralib@1".
// Note there's a transitive dependency on 'corelib@1' which is handled automatically.
pathload()->addSearchDir(__DIR__ . '/lib')->addPackage('extralib@1', 'Example\\');

// Sneaky - we declared dependence on "extralib@1" but actually used a resource from transitive-dependency "corelib@1".
Example\CoreLib::greet();
