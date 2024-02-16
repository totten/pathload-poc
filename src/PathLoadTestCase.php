<?php

namespace PathLoad\Test;

class PathLoadTestCase extends \PHPUnit\Framework\TestCase {

  protected function setUp(): void {
    $this->assertFalse(isset($GLOBALS['_PathLoad']), 'PathLoad has not been initialized yet.');
  }

  protected function tearDown(): void {
    if (isset($GLOBALS['_PathLoad']['top'])) {
      $this->assertClassloaderHasNoDuplicates($GLOBALS['_PathLoad']['top']->psr4Classloader);
    }
  }

  public function expectOutputLines(array $lines) {
    $this->expectOutputString(implode("\n", $lines) . "\n");
  }

  public function assertLoaded(array $majorNamesFiles): void {
    foreach ($majorNamesFiles as $majorName => $file) {
      $actual = pathload()->resolvedPackages[$majorName]->file ?? NULL;
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
