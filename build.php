#!/usr/bin/env php
<?php

namespace PathLoad;

function main() {
  if (!is_dir('dist')) {
    mkdir('dist');
  }
  $dir = __DIR__;

  $template = file_get_contents($dir . '/src/template.php');
  $classSources = [
    file_get_contents($dir . '/src/PathLoad.php'),
    // file_get_contents(__DIR__ . '/src/PathLoad.php'),
    file_get_contents($dir . '/src/Psr4Autoloader.php'),
  ];

  $makeClasses = function ($m) use ($classSources) {
    $classes = '';
    foreach ($classSources as $classSource) {
      $classSource = normalize($classSource, $m[1]);
      $classes .= $classSource . "\n";
    }
    return $classes;
  };
  $full = preg_replace_callback(';\n(\s*)//CLASSES//;', $makeClasses, $template);
  file_put_contents("$dir/dist/pathload.php", $full);
  file_put_contents("$dir/dist/pathload.min.php", trimWhitespace(stripComments($full)));
}

function stripComments(string $phpSource): string {
  $tokens = token_get_all($phpSource);
  $minifiedCode = '';

  foreach ($tokens as $token) {
    if (is_array($token)) {
      list($id, $text) = $token;
      if ($id !== T_COMMENT && $id !== T_DOC_COMMENT) {
        $minifiedCode .= $text;
      }
    }
    else {
      $minifiedCode .= $token;
    }
  }

  return $minifiedCode;
}

function trimWhitespace(string $phpSource): string {
  $tokens = token_get_all($phpSource);
  $minifiedCode = '';

  foreach ($tokens as $token) {
    if (is_array($token)) {
      list($id, $text) = $token;
      if ($id == T_WHITESPACE) {
        // $minifiedCode .= $text;
        $minifiedCode .= preg_replace(";[ \n]*\n([^\n]);", "\n$1", $text);
      }
      else {
        $minifiedCode .= $text;
      }
    }
    else {
      $minifiedCode .= $token;
    }
  }

  return $minifiedCode;
}

function indent(string $phpSource, string $prefix): string {
  $tokens = token_get_all($phpSource);
  $formattedCode = '';

  foreach ($tokens as $token) {
    if (is_array($token)) {
      list($id, $text) = $token;
      if ($id === T_WHITESPACE || $id == T_DOC_COMMENT) {
        $formattedCode .= str_replace("\n", "\n$prefix", $text);
        // } elseif($id == T_DOC_COMMENT) {
      }
      else {
        $formattedCode .= $text;
      }
    }
    else {
      $formattedCode .= $token;
    }
  }

  return $formattedCode;
}

function normalize(string $classSource, string $prefix): string {
  $classSource = indent($classSource, $prefix);
  $classSource = trimWhitespace($classSource);
  $classSource = str_replace("<" . "?php", "", $classSource);
  $classSource = str_replace("namespace PathLoad;", "", $classSource);
  return $classSource;
}

main();
