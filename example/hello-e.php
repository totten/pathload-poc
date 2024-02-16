#!/usr/bin/env php
<?php

// Enable Pathload API.
($GLOBALS['_PathLoad'][0] ?? require dirname(__DIR__) . '/dist/pathload-0.php');

// Setup a batch of oddball libraries
require __DIR__ . '/monorepo-1.4.0/monorepo.php';

// Sneaky - we declared dependence on "extralib@1" but actually used a resource from transitive-dependency "corelib@1".
Mono\ArrayStuff\ArrayStuff::loopSomething();
