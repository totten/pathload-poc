#!/usr/bin/env php
<?php
namespace RunPhpt;

// ABOUT: Run a single "*.phpt" file.
//
// USAGE: ./scripts/run-phpt.php [FILE]
// EXAMPLE: ./scripts/run-phpt.php tests/Foobar.phpt
//
// You can+should run the suite through phpunit. However, when loading the tests
// into my version of PHPStorm, I couldn't figure a godo way to debug. So instead,
// you can setup a run configuration for debugging this script.

function runFile($file) {
  $result = '';
  ob_start(function($buffer) use (&$result) {
    fputs(STDERR, $buffer);
    fflush(STDERR);
    $result .= $buffer;
  }, 32);
  require $file;
  ob_end_flush();
  return $result;
}

function splitOutput(string $out): array {
  $key = 'START';
  $result = [$key => ''];

  $lines = explode("\n", $out);
  foreach ($lines as $line) {
    if (preg_match(';^--(\w+)--$;', $line, $m)) {
      $key = $m[1];
      $result[$key] = '';
    }
    else {
      $result[$key] .= $line . "\n";
    }
  }

  return $result;
}

require_once dirname(__DIR__) . '/tests/bootstrap.php';
$out = runFile($argv[1]);
$parts = splitOutput($out);
$ok = trim($parts['FILE']) === trim($parts['EXPECT']);
fputs(STDERR, "\n\n");
fputs(STDERR, $ok ? "OK\n" : "Different\n");
exit($ok ? 0 : 1);