#!/usr/bin/env php
<?php

($GLOBALS['_PathLoad'] ?? require __DIR__ . '/../src/pathload.php')
  ->append(__DIR__ . '/dist')
  ->loadPackage('extralib@1');

Example\ExtraLib::doStuff();
