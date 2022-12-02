#!/usr/bin/env php
<?php

// Enable Pathload API.
($GLOBALS['_PathLoad'] ?? require __DIR__ . '/../src/pathload.php');
// Add "./lib/" to the search-path. Bind "Example\\" to "extralib@1".
// Note there's a transitive dependency on 'corelib@1' which is handled automatically.
pathload()->append(__DIR__ . '/lib')->addPackage('Example\\', 'extralib@1');

// Sneaky - we declared dependence on "extralib@1" but actually used a resource from transitive-dependency "corelib@1".
Example\CoreLib::greet();
