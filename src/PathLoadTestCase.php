<?php

namespace PathLoad\Test;

class PathLoadTestCase extends \PHPUnit\Framework\TestCase {

  protected function setUp(): void {
    $this->assertFalse(isset($GLOBALS['_PathLoad']), 'PathLoad has not been initialized yet.');
  }

  public function expectOutputLines(array $lines) {
    $this->expectOutputString(implode("\n", $lines) . "\n");
  }

  public function assertLoaded(array $majorNamesFiles): void {
    foreach ($majorNamesFiles as $majorName => $file) {
      $actual = pathload()->resolvedPackages[$majorName]['file'] ?? NULL;
      $this->assertEquals($actual, $file);
    }
  }

}
