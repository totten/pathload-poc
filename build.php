#!/usr/bin/env php
<?php

namespace PathLoad;

function main() {
  if (!is_dir('dist')) {
    mkdir('dist');
  }
  $dir = __DIR__;

  $template = read('template.php');
  $full = evalTemplate($template, [
    "\n",
    read('funcs.php'),
    read('PathLoadInterface.php'),
    read('PathLoad.php'),
    read('Psr4Autoloader.php'),
  ]);
  file_put_contents("$dir/dist/pathload.php", $full);

  $min = trimWhitespace(evalTemplate($template, [
    "\n",
    stripComments(read('funcs.php')),
    read('PathLoadInterface.php'),
    stripComments(read('PathLoad.php')),
    stripComments(read('Psr4Autoloader.php')),
  ]));
  file_put_contents("$dir/dist/pathload.min.php", $min);
}

function read($file): string {
  return file_get_contents(__DIR__ . '/src/' . $file);
}

function evalTemplate(string $template, array $phpSources): string {
  $makeClasses = function ($m) use ($phpSources) {
    $classes = '';
    foreach ($phpSources as $phpSource) {
      $classes .= normalize($phpSource, $m[1]) . "\n";
    }
    $classes = trimWhitespace($classes);
    return $classes;
  };
  return preg_replace_callback(';\n(\s*)//CLASSES//;', $makeClasses, $template);
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
  $phpSource = preg_replace("~namespace PathLoad[\w\\\]*;~", "", $phpSource);
  $phpSource = trim($phpSource, "\n") . "\n";
  return $phpSource;
}

main();
