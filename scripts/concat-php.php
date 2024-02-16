#!/usr/bin/env php
<?php

// Combine several PHP files into one file.
//
// Usage: concat-php.php [file1 file2...]
// Example: concat-php.php a.php b.php c.php > all.php
//
// Currently only supports pure-logic files. Not tested with template-files.

define('ST_TEXT', 'ST_TEXT');
define('ST_TOP', 'ST_TOP');
define('ST_NS_DECL', 'ST_NS_DECL');
define('ST_NS_NESTED_BODY', 'ST_NS_NESTED_BODY');
define('ST_NS_TOP_BODY', 'ST_NS_TOP_BODY');

/**
 * @param string $phpSource
 * @return array
 *   For each "namespace" segment in the file, return a tuple:
 *     [0 => string $nsName, 1 => string $nsCode].
 *   If there are no namespaces, then $nsName is ''.
 */
function splitByNamespace(string $phpSource): array {
  $tokens = token_get_all($phpSource);

  $allPairs = [];

  $namespace = '';
  $interiorSourceCode = '';
  $exteriorSourceCode = '';
  $state = ST_TEXT;
  $braceCount = 0;

  foreach ($tokens as $token) {
    [$tokenId, $tokenValue] = is_array($token) ? $token : [$token, $token];

    switch ($state) {
      case ST_TEXT:
        if ($tokenId === T_OPEN_TAG) {
          $state = ST_TOP;
        }
        break;

      case ST_TOP:
        if ($tokenId === T_NAMESPACE) {
          $state = ST_NS_DECL;
        }
        else {
          $exteriorSourceCode .= $tokenValue;
        }
        break;

      case ST_NS_DECL:
        if ($tokenId === T_STRING || $tokenId === T_NS_SEPARATOR) {
          $namespace .= $tokenValue;
        }
        elseif ($token === ';') {
          $state = ST_NS_TOP_BODY;
        }
        elseif ($token === '{') {
          $state = ST_NS_NESTED_BODY;
          $braceCount++;
          $interiorSourceCode .= $token;
        }
        break;

      case ST_NS_TOP_BODY:
        $interiorSourceCode .= $tokenValue;
        break;

      case ST_NS_NESTED_BODY:
        $interiorSourceCode .= $tokenValue;

        if ($token === '{') {
          $braceCount++;
        }
        elseif ($tokenValue === '}') {
          $braceCount--;
        }

        if ($braceCount === 0) {
          $allPairs[] = [$namespace, trim($interiorSourceCode) . "\n"];
          $namespace = '';
          $interiorSourceCode = '';
          $state = ST_TOP;
        }
        break;
    }
  }

  if ($namespace !== '') {
    $allPairs[] = [$namespace, trim($interiorSourceCode) . "\n"];
  }
  elseif (trim($exteriorSourceCode) !== '') {
    $allPairs[] = ['', trim($exteriorSourceCode) . "\n"];
  }

  return $allPairs;
}

function main($prog, ...$files): int {
  echo '<' . "?php\n";

  foreach ($files as $file) {
    $parts = splitByNamespace(file_get_contents($file));
    foreach ($parts as $part) {
      [$namespace, $code] = $part;

      if ($namespace !== '') {
        echo "namespace $namespace {\n";
      }
      else {
        echo "namespace {\n";
      }
      echo $code;
      echo "}\n";
    }
  }

  return 0;
}


exit(main(...$argv));
