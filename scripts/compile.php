#!/usr/bin/env php
<?php

namespace PathLoad\Build;

// This should give the real/default version. However, for purposes
// of hacking/experimenting, you can override the version# during compilation.
define('PATHLOAD_VERSION', getenv('PATHLOAD_VERSION') ?: (require dirname(__DIR__) . '/src/version.php'));

/**
 * Ex: prjdir('dist')
 * Ex: prjdir('example/dist')
 * Ex: prjdir('example', 'dist')
 *
 * @param array $parts
 * @return string
 */
function prjdir(...$parts): string {
  $prjdir = dirname(__DIR__);
  array_unshift($parts, $prjdir);
  return implode('/', $parts);
}

function main() {
  if (!is_dir('dist')) {
    mkdir('dist');
  }
  $dist = prjdir('dist');
  $version = PATHLOAD_VERSION;

  $full = evalTemplate(FALSE);
  file_put_contents("$dist/pathload-latest.php", $full);
  copy("$dist/pathload-latest.php", "$dist/pathload-$version.php");

  $min = evalTemplate(TRUE);
  file_put_contents("$dist/pathload-latest.min.php", $min);
  copy("$dist/pathload-latest.min.php", "$dist/pathload-$version.min.php");
}

function evalTemplate(bool $minify): string {
  $cleanup = ($minify ? '\PathLoad\Build\stripAllComments' : '\PathLoad\Build\stripInternalComments');

  $template = read('polyfill-template.php');
  $phpSources = [
    'PathLoadInterface' => read('PathLoadInterface.php'),

    // TODO: For the rest, maybe just glob it...
    'funcs' => $cleanup(read('Vn/funcs.php')),
    'PathLoad' => $cleanup(read('Vn/PathLoad.php')),
    'Scanner' => $cleanup(read('Vn/Scanner.php')),
    'Package' => $cleanup(read('Vn/Package.php')),
    'Versions' => $cleanup(read('Vn/Versions.php')),
    'ClassLoader' => $cleanup(read('Vn/ClassLoader.php')),
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

function read($file): string {
  return file_get_contents(prjdir('src', $file));
}

function stripAllComments(string $phpSource): string {
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

function stripInternalComments(string $phpSource): string {
  $lines = explode("\n", $phpSource);
  $lines = preg_grep(';\w*//internal//;', $lines, PREG_GREP_INVERT);
  return implode("\n", $lines);
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
