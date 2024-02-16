<?php

// This is an example of using an alternative library layout.
// For example, you maintain a set of related libraries with matched version#s.

pathload()
  ->addSearchItem('mono-array', '1.4.0', __DIR__ . '/array')
  ->addSearchItem('mono-file', '1.4.0', __DIR__ . '/file');

pathload()
  ->addPackage('mono-array@1', 'Mono\\ArrayStuff\\')
  ->addPackage('mono-file@1', 'Mono\\File\\');