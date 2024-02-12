<?php
namespace PathLoad\Test;

class ExtraLibTest extends PathLoadTestCase {

  public function testAddSearchDir_Phar_v123() {
    $libDir = buildLibDir(__FUNCTION__, [
      'corelib@1.0.0' => 'phar',
      'corelib@1.2.3' => 'phar',
      'extralib@1.0.0' => 'phar',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));

    pathload()
      ->addSearchDir($libDir)
      ->addPackage('corelib@1', 'Example\\')
      ->addPackage('extralib@1', 'Example\\');

    $this->assertLoaded(['corelib@1' => NULL, 'extralib@1' => NULL]);

    $this->expectOutputLines([
      'hello from corelib v1.2.3',
      'and hello from extralib v1.0.0',
    ]);
    \Example\ExtraLib::doStuff();

    $this->assertLoaded(['corelib@1' => "$libDir/corelib@1.2.3.phar", 'extralib@1' => "$libDir/extralib@1.0.0.phar"]);
  }

  public function testAddSearchDir_SplitA() {
    $libDirA = buildLibDir(__FUNCTION__ . '/a', [
      'corelib@1.0.0' => 'phar',
      'extralib@1.0.0' => 'phar',
    ]);

    $libDirB = buildLibDir(__FUNCTION__ . '/b', [
      'corelib@1.6.0' => 'phar',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));

    pathload()
      ->addSearchDir($libDirA)
      ->addSearchDir($libDirB)
      ->addPackage('corelib@1', 'Example\\')
      ->addPackage('extralib@1', 'Example\\');

    $this->assertLoaded(['corelib@1' => NULL, 'extralib@1' => NULL]);

    $this->expectOutputLines([
      'hello from corelib v1.6.0',
      'and hello from extralib v1.0.0',
    ]);
    \Example\ExtraLib::doStuff();

    $this->assertLoaded(['corelib@1' => "$libDirB/corelib@1.6.0.phar", 'extralib@1' => "$libDirA/extralib@1.0.0.phar"]);
  }

  public function testAddSearchDir_SplitB() {
    $libDirA = buildLibDir(__FUNCTION__ . '/a', [
      'corelib@1.2.3' => 'dir',
      'corelib@1.0.0' => 'phar',
    ]);

    $libDirB = buildLibDir(__FUNCTION__ . '/b', [
      'extralib@1.0.0' => 'php',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));

    pathload()
      ->addSearchDir($libDirA)
      ->addSearchDir($libDirB)
      ->addPackage('corelib@1', 'Example\\')
      ->addPackage('extralib@1', 'Example\\');

    $this->assertLoaded(['corelib@1' => NULL, 'extralib@1' => NULL]);

    $this->expectOutputLines([
      'hello from corelib v1.2.3',
      'and hello from extralib v1.0.0',
    ]);
    \Example\ExtraLib::doStuff();

    $this->assertLoaded(['corelib@1' => "$libDirA/corelib@1.2.3", 'extralib@1' => "$libDirB/extralib@1.0.0.php"]);
  }

}
