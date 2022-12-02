#!/usr/bin/env php
<?php

($GLOBALS['_PathLoad'] ?? require __DIR__ . '/../src/pathload.php');
pathload()->append(__DIR__ . '/lib')->addPackage('Example\\', 'extralib@1');

Example\CoreLib::greet();
