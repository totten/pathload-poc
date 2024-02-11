#!/usr/bin/env php
<?php

namespace PathLoad;

function main() {
  if (!is_dir('dist')) {
    mkdir('dist');
  }
  $dir = __DIR__;

  $template = file_get_contents($dir . '/src/template.php');
  $phpSources = [
    "\n",
    file_get_contents($dir . '/src/funcs.php'),
    file_get_contents($dir . '/src/PathLoad.php'),
    file_get_contents($dir . '/src/Psr4Autoloader.php'),
  ];

  $makeClasses = function ($m) use ($phpSources) {
    $classes = '';
    foreach ($phpSources as $phpSource) {
      $classes .= normalize($phpSource, $m[1]) . "\n";
    }
    $classes = trimWhitespace($classes);
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

  foreach ($tokens as $tokId => $token) {
    if (!is_array($token)) {
      $minifiedCode .= $token;
      continue;
    }

    list($id, $text) = $token;
    if ($id == T_WHITESPACE) {
      $text = preg_replace(";[ \n]*\n([^\n]);", "\n$1", $text);
    }
    $minifiedCode .= $text;
  }

  return rtrim($minifiedCode, " ");
}

function indent(string $phpSource, string $prefix): string {
  $tokens = token_get_all($phpSource);
  $formattedCode = '';

  foreach ($tokens as $token) {
    if (is_array($token)) {
      list($id, $text) = $token;
      if ($id === T_WHITESPACE || $id == T_DOC_COMMENT || $id == T_COMMENT) {
        $formattedCode .= str_replace("\n", "\n$prefix", $text);
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

function normalize(string $phpSource, string $prefix): string {
  $phpSource = trimWhitespace(indent($phpSource, $prefix));
  $phpSource = str_replace("<" . "?php", "", $phpSource);
  $phpSource = str_replace("namespace PathLoad;", "", $phpSource);
  $phpSource = trim($phpSource, "\n") . "\n";
  return $phpSource;
}

main();
