<?php
namespace PathLoad\Test;

/**
 * The "reloadable" package is an example that allows itself to be
 * reloaded -- even for applying new increments within a major version.
 *
 * This is intended for a particular niche: libraries that are loaded
 * during system-bootstrap, module-installation, and other sensitive moments.
 */
class ReloadableTest extends PathLoadTestCase {

  public function testReloadable_v100() {
    $libDir = buildLibDir(__FUNCTION__, [
      'reloadable@1.0.0' => 'phar',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require currentPolyfill())
      ->addSearchDir($libDir)
      ->loadPackage('reloadable@1');

    $this->assertLoaded(['reloadable@1' => "$libDir/reloadable@1.0.0.phar"]);

    $this->expectOutputLines(['Hello world from reloadable v1.0.0']);
    global $Reloadable;
    $Reloadable->greet('world');
  }

  public function testReloadable_v130() {
    $libDir = buildLibDir(__FUNCTION__, [
      'reloadable@1.0.0' => 'phar',
      'reloadable@1.3.0' => 'phar',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require currentPolyfill())
      ->addSearchDir($libDir)
      ->loadPackage('reloadable@1');

    $this->assertLoaded(['reloadable@1' => "$libDir/reloadable@1.3.0.phar"]);

    $this->expectOutputLines(['Hello world from reloadable v1.3.0']);
    global $Reloadable;
    $Reloadable->greet('world');
  }

  public function testReloadable_Both() {
    $libDirA = buildLibDir(__FUNCTION__ . '/a', [
      'reloadable@1.0.0' => 'phar',
    ]);
    $libDirB = buildLibDir(__FUNCTION__ . '/b', [
      'reloadable@1.3.0' => 'phar',
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require currentPolyfill());
    pathload()->addSearchDir($libDirA);
    pathload()->loadPackage('reloadable@1');
    global $Reloadable;

    $this->assertLoaded(['reloadable@1' => "$libDirA/reloadable@1.0.0.phar"]);

    $this->expectOutputLines([
      'Hello world from reloadable v1.0.0',
      'Hello world from reloadable v1.3.0',
    ]);
    $Reloadable->greet('world');

    pathload()->addSearchDir($libDirB);
    pathload()->loadPackage('reloadable@1', TRUE);

    $Reloadable->greet('world');
  }

}
