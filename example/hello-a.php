#!/usr/bin/env php
<?php

printf("[Run %s]\n", __FILE__);

($GLOBALS['_PathLoad'] ?? require __DIR__ . '/../src/pathload.php');
pathload()->addPackage('Example\\', 'corelib@1', __DIR__ . '/dist');

Example\CoreLib::greet();
