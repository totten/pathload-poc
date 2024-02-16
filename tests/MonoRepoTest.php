<?php
namespace PathLoad\Test;

/**
 * TIP: Disable this annotation when debugging PHPStorm. But you can only run one test.
 * FIXME: runTestsInSeparateProcesses
 */
class MonoRepoTest extends PathLoadTestCase {

  public function testAutoLoader_v100() {
    ($GLOBALS['_PathLoad']['top'] ?? require currentPolyfill());
    require srcPath('example/monorepo-1.0.0/monorepo.php');

    $this->assertLoaded(['mono-array@1' => NULL, 'mono-file@1' => NULL]);

    $this->expectOutputLines([
      'hello from mono-file v1.0.0',
      'hello from mono-array v1.0.0',
    ]);

    \Mono\File\FileStuff::loopSomething();
    $this->assertLoaded([
      'mono-file@1' => srcPath('example/monorepo-1.0.0/file'),
      'mono-array@1' => NULL,
    ]);

    \Mono\ArrayStuff\ArrayStuff::loopSomething();
    $this->assertLoaded([
      'mono-file@1' => srcPath('example/monorepo-1.0.0/file'),
      'mono-array@1' => srcPath('example/monorepo-1.0.0/array'),
      ]);
  }

  public function testAutoLoader_v140() {
    ($GLOBALS['_PathLoad']['top'] ?? require currentPolyfill());
    require srcPath('example/monorepo-1.4.0/monorepo.php');

    $this->assertLoaded(['mono-array@1' => NULL, 'mono-file@1' => NULL]);

    $this->expectOutputLines([
      'hello from mono-array v1.4.0',
      'hello from mono-file v1.4.0',
    ]);

    \Mono\ArrayStuff\ArrayStuff::loopSomething();
    $this->assertLoaded([
      'mono-array@1' => srcPath('example/monorepo-1.4.0/array'),
      'mono-file@1' => NULL,
    ]);

    \Mono\File\FileStuff::loopSomething();
    $this->assertLoaded([
      'mono-array@1' => srcPath('example/monorepo-1.4.0/array'),
      'mono-file@1' => srcPath('example/monorepo-1.4.0/file'),
    ]);
  }

  public function testAutoLoader_Both() {
    ($GLOBALS['_PathLoad']['top'] ?? require currentPolyfill());
    require srcPath('example/monorepo-1.0.0/monorepo.php');
    require srcPath('example/monorepo-1.4.0/monorepo.php');

    $this->assertLoaded(['mono-array@1' => NULL, 'mono-file@1' => NULL]);

    $this->expectOutputLines([
      'hello from mono-array v1.4.0',
      'hello from mono-file v1.4.0',
    ]);

    \Mono\ArrayStuff\ArrayStuff::loopSomething();
    $this->assertLoaded([
      'mono-array@1' => srcPath('example/monorepo-1.4.0/array'),
      'mono-file@1' => NULL,
    ]);

    \Mono\File\FileStuff::loopSomething();
    $this->assertLoaded([
      'mono-array@1' => srcPath('example/monorepo-1.4.0/array'),
      'mono-file@1' => srcPath('example/monorepo-1.4.0/file'),
    ]);

  }

}
