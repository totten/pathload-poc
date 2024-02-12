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

    pathload()->addSearchDir($libDir)
      ->addPackage('corelib@1', 'Example\\')
      ->addPackage('extralib@1', 'Example\\');

    $this->expectOutputLines([
      'hello from corelib v1.2.3',
      'and hello from extralib v1.0.0',
    ]);
    \Example\ExtraLib::doStuff();
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

    pathload()->addSearchDir($libDirA)
      ->addSearchDir($libDirB)
      ->addPackage('corelib@1', 'Example\\')
      ->addPackage('extralib@1', 'Example\\');

    $this->expectOutputLines([
      'hello from corelib v1.6.0',
      'and hello from extralib v1.0.0',
    ]);
    \Example\ExtraLib::doStuff();
  }

}
