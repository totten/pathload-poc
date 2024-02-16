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

    $this->assertFalse(isset($GLOBALS['_PathLoad']), 'PathLoad should not be active yet.');
  }

  protected function tearDown(): void {
    restore_error_handler();

    if (isset($GLOBALS['_PathLoad']['top'])) {
      $this->assertClassloaderHasNoDuplicates($GLOBALS['_PathLoad']['top']);
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
   * @param \PathLoad\Vn\PathLoad $pathLoad
   * @return void
   */
  protected function assertClassloaderHasNoDuplicates($pathLoad): void {
    $counts = [];
    foreach ($pathLoad->psr4->prefixes as $ns => $paths) {
      foreach ($paths as $path) {
        $sig = "psr4: $ns => $path";
        $counts[$sig] = 1 + ($counts[$sig] ?? 0);
      }
    }
    foreach ($pathLoad->psr0->paths as $bucket => $prefixPaths) {
      foreach ($prefixPaths as $ns => $paths) {
        foreach ($paths as $path) {
          $sig = "psr0: $ns => $path";
          $counts[$sig] = 1 + ($counts[$sig] ?? 0);
        }
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
