#!/usr/bin/env php
<?php

namespace PathLoad\Build;

define('PATHLOAD_VERSION', 0);

function main() {
  if (!is_dir('dist')) {
    mkdir('dist');
  }
  $dir = __DIR__;

  $full = evalTemplate(FALSE);
  file_put_contents("$dir/dist/pathload.php", $full);

  $min = evalTemplate(TRUE);
  file_put_contents("$dir/dist/pathload.min.php", $min);
}

function evalTemplate(bool $stripComments): string {
  $cleanup = ($stripComments ? '\PathLoad\Build\stripComments' : '\PathLoad\Build\identity');

  $template = read('template.php');
  $phpSources = [
    'PathLoadInterface' => read('PathLoadInterface.php'),
    'funcs' => $cleanup(read('funcs.php')),
    'PathLoad' => $cleanup(read('PathLoad.php')),
    'Psr4Autoloader' => $cleanup(read('Psr4Autoloader.php')),
  ];

  $includeCode = function ($m) use ($phpSources) {
    return "\n" . normalize($phpSources[$m[2]], $m[1]) . "\n";
  };
  $result = preg_replace_callback(';\n(\s*)//INCLUDE:(\w+)//;', $includeCode, $template);
  $result = strtr($result, [
    'PATHLOAD_NS' => 'PathLoad\V' . PATHLOAD_VERSION,
    'PATHLOAD_VERSION' => PATHLOAD_VERSION,
  ]);
  return trimWhitespace($result);
}

function identity($x) {
  return $x;
}

function read($file): string {
  return file_get_contents(__DIR__ . '/src/' . $file);
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
  $phpSource = trimWhitespace($phpSource);
  return $phpSource;
}

main();
