#!/usr/bin/env php
<?php

// In this example, we load the file several times in hopes of mischief.
// This requires mocking pathload v1, which isn't actually a thing yet,
// so it won't work without someone manual coercision.

(require __DIR__ . '/../dist/pathload-0.php');
(require __DIR__ . '/../dist/pathload-1.php');

pathload()->addSearchDir(__DIR__ . '/lib')->addPackage('extralib@1', 'Example\\');

(require __DIR__ . '/../dist/pathload-0.php');
(require __DIR__ . '/../dist/pathload-1.php');

Example\CoreLib::greet();

(require __DIR__ . '/../dist/pathload-0.php');
(require __DIR__ . '/../dist/pathload-1.php');
