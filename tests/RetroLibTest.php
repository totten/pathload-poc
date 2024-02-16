<?php
namespace PathLoad\Test;

/**
 * TIP: Disable this annotaiton when debugging PHPStorm. But you can only run one test.
 * FIXME: runTestsInSeparateProcesses
 */
class RetroLibTest extends PathLoadTestCase {

  protected function setUpRetroLib(string $version, string $type): string {
    $libDir = buildLibDir(__FUNCTION__, [
      'retrolib@' . $version => $type,
    ]);

    ($GLOBALS['_PathLoad']['top'] ?? require currentPolyfill());
    pathload()->addSearchDir($libDir);
    pathload()->addPackage('retrolib@1', ['RetroSlash\\', 'RetroScore_']);

    return $libDir;
  }

  public function testAutoload_Dir_v100_Slash() {
    $libDir = $this->setUpRetroLib('1.0.0', 'dir');
    $this->expectOutputLines([
      'hello from retrolib\'s RetroSlash\Example\Greeter v1.0.0',
    ]);
    \RetroSlash\Example\Greeter::greet();
    $this->assertLoaded(['retrolib@1' => "$libDir/retrolib@1.0.0"]);
  }

  public function testAutoload_Dir_v100_Score() {
    $libDir = $this->setUpRetroLib('1.0.0', 'dir');
    $this->expectOutputLines([
      'hello from retrolib\'s RetroScore_Example_Greeter v1.0.0',
    ]);
    \RetroScore_Example_Greeter::greet();
    $this->assertLoaded(['retrolib@1' => "$libDir/retrolib@1.0.0"]);
  }

  public function testAutoload_Dir_v100_BothA() {
    $libDir = $this->setUpRetroLib('1.0.0', 'dir');
    $this->expectOutputLines([
      'hello from retrolib\'s RetroSlash\Example\Greeter v1.0.0',
      'hello from retrolib\'s RetroScore_Example_Greeter v1.0.0',
    ]);
    \RetroSlash\Example\Greeter::greet();
    \RetroScore_Example_Greeter::greet();
    $this->assertLoaded(['retrolib@1' => "$libDir/retrolib@1.0.0"]);
  }

  public function testAutoload_Dir_v100_BothB() {
    $libDir = $this->setUpRetroLib('1.0.0', 'dir');
    $this->expectOutputLines([
      'hello from retrolib\'s RetroScore_Example_Greeter v1.0.0',
      'hello from retrolib\'s RetroSlash\Example\Greeter v1.0.0',
    ]);
    \RetroScore_Example_Greeter::greet();
    \RetroSlash\Example\Greeter::greet();
    $this->assertLoaded(['retrolib@1' => "$libDir/retrolib@1.0.0"]);
  }

  public function testAutoload_Dir_v110_Slash() {
    $libDir = $this->setUpRetroLib('1.1.0', 'dir');
    $this->expectOutputLines([
      'hello from retrolib\'s RetroSlash\Example\Greeter v1.1.0',
    ]);
    \RetroSlash\Example\Greeter::greet();
    $this->assertLoaded(['retrolib@1' => "$libDir/retrolib@1.1.0"]);
  }

  public function testAutoload_Dir_v110_Score() {
    $libDir = $this->setUpRetroLib('1.1.0', 'dir');
    $this->expectOutputLines([
      'hello from retrolib\'s RetroScore_Example_Greeter v1.1.0',
    ]);
    \RetroScore_Example_Greeter::greet();
    $this->assertLoaded(['retrolib@1' => "$libDir/retrolib@1.1.0"]);
  }

}
