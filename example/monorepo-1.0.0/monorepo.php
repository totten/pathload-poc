<?php

// This is an example of using an alternative library layout.
// For example, you maintain a set of related libraries with matched version#s.

pathload()
  ->addSearchItem('mono-array', '1.0.0', __DIR__ . '/array')
  ->addSearchItem('mono-file', '1.0.0', __DIR__ . '/file');

pathload()
  ->addNamespace('mono-array@1', 'Mono\\ArrayStuff\\')
  ->addNamespace('mono-file@1', 'Mono\\File\\');
