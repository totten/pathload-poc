<?php
namespace PathLoad\Test;

/**
 * TIP: Disable this annotaiton when debugging PHPStorm. But you can only run one test.
 * FIXME: runTestsInSeparateProcesses
 */
class CoreLibTest extends PathLoadTestCase {

  public function testLoadPackage_Phar_v123() {
    $libDir = buildLibDir(__FUNCTION__, [
      'corelib@1.0.0' => 'phar',
      'corelib@1.2.3' => 'phar',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'))
      ->addSearchDir($libDir)
      ->loadPackage('corelib@1');

    // This example uses loadPackage(). It doesn't involve namespace-autoloading.

    $this->expectOutputLines(['hello from corelib v1.2.3']);
    \Example\CoreLib::greet();
  }

  public function testAddPackage_Phar_v123() {
    $libDir = buildLibDir(__FUNCTION__, [
      'corelib@1.0.0' => 'phar',
      'corelib@1.2.3' => 'phar',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));
    pathload()->addPackage('corelib@1', 'Example\\', $libDir);

    $this->expectOutputLines(['hello from corelib v1.2.3']);
    \Example\CoreLib::greet();
  }

  public function testAddPackage_Phar_v160() {
    $libDir = buildLibDir(__FUNCTION__, [
      'corelib@1.0.0' => 'phar',
      'corelib@1.2.3' => 'phar',
      'corelib@1.6.0' => 'phar',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));
    pathload()->addPackage('corelib@1', 'Example\\', $libDir);

    $this->expectOutputLines(['hello from corelib v1.6.0']);
    \Example\CoreLib::greet();
  }

  public function testAddPackage_Dir_v123() {
    $libDir = buildLibDir(__FUNCTION__, [
      'corelib@1.0.0' => 'dir',
      'corelib@1.2.3' => 'dir',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));
    pathload()->addPackage('corelib@1', 'Example\\', $libDir);

    $this->expectOutputLines(['hello from corelib v1.2.3']);
    \Example\CoreLib::greet();
  }

  public function testAddSearchDir_Phar_v123() {
    $libDir = buildLibDir(__FUNCTION__, [
      'corelib@1.0.0' => 'dir',
      'corelib@1.2.3' => 'dir',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));
    pathload()->addSearchDir($libDir);
    pathload()->addPackage('corelib@1', 'Example\\');

    $this->expectOutputLines(['hello from corelib v1.2.3']);
    \Example\CoreLib::greet();
  }

  public function testAddSearchDir_SplitA() {
    $libDirA = buildLibDir(__FUNCTION__ . '/a', [
      'corelib@1.2.3' => 'dir',
    ]);
    $libDirB = buildLibDir(__FUNCTION__ . '/b', [
      'corelib@1.0.0' => 'dir',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));
    pathload()->addSearchDir($libDirA)->addSearchDir($libDirB);
    pathload()->addPackage('corelib@1', 'Example\\');

    $this->expectOutputLines(['hello from corelib v1.2.3']);
    \Example\CoreLib::greet();
  }

  public function testAddSearchDir_SplitB() {
    $libDirA = buildLibDir(__FUNCTION__ . '/a', [
      'corelib@1.0.0' => 'dir',
    ]);
    $libDirB = buildLibDir(__FUNCTION__ . '/b', [
      'corelib@1.6.0' => 'dir',
    ]);
    $libDirC = buildLibDir(__FUNCTION__ . '/c', [
      'corelib@1.2.3' => 'dir',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require srcPath('dist/pathload-latest.php'));
    pathload()->addSearchDir($libDirA)->addSearchDir($libDirB)->addSearchDir($libDirC);
    pathload()->addPackage('corelib@1', 'Example\\');

    $this->expectOutputLines(['hello from corelib v1.6.0']);
    \Example\CoreLib::greet();
  }

}
