<?php

namespace PathLoad\Test;

class PathLoadTestCase extends \PHPUnit\Framework\TestCase {

  protected $actualWarnings = [];

  protected $expectWarnings = [];

  protected function setUp(): void {
    $this->actualWarnings = [];
    $this->expectWarnings = [];
    set_error_handler(function (int $errno, string $errstr) {
      $this->actualWarnings[] = $errstr;
    }, E_USER_WARNING);

    $this->assertFalse(isset($GLOBALS['_PathLoad']), 'PathLoad has not been initialized yet.');
  }

  protected function tearDown(): void {
    restore_error_handler();

    if (isset($GLOBALS['_PathLoad']['top'])) {
      /** @var \PathLoad\Vn\PathLoad $top */
      $top = $GLOBALS['_PathLoad']['top'];
      $this->assertClassloaderHasNoDuplicates($top->classLoader);
    }

    $this->assertEquals($this->expectWarnings, $this->actualWarnings, "All warnings should be expected");
  }

  public function expectOutputLines(array $lines) {
    $this->expectOutputString(implode("\n", $lines) . "\n");
  }

  public function assertLoaded(array $majorNamesFiles): void {
    foreach ($majorNamesFiles as $majorName => $file) {
      $actual = pathload()->loadedPackages[$majorName]->file ?? NULL;
      $this->assertEquals($actual, $file);
    }
  }

  /**
   * @param $classloader
   * @return void
   */
  protected function assertClassloaderHasNoDuplicates($classloader): void {
    $counts = [];
    foreach ($classloader->prefixes as $ns => $paths) {
      foreach ($paths as $path) {
        $sig = "$ns => $path";
        $counts[$sig] = 1 + ($counts[$sig] ?? 0);
      }
    }
    $extras = [];
    foreach ($counts as $sig => $count) {
      if ($count > 1) {
        $extras[] = $sig;
      }
    }
    $this->assertEquals([], $extras, "Folders should only be registered once.");
  }

}
