<?php
namespace {
class RetroScore_Example_Greeter {

  public static function greet() {
    echo "hello from retrolib's RetroScore_Example_Greeter v1.1.0\n";
  }

}
}
namespace {
pathload()->activatePackage('retrolib@1', __DIR__, [
  'autoload' => [
    'psr-0' => [
      'RetroScore_' => ['score/'],
      'RetroSlash\\' => 'slash/',
    ],
  ]
]);
}
namespace RetroSlash\Example {
class Greeter {

  public static function greet() {
    echo "hello from retrolib's RetroSlash\\Example\\Greeter v1.1.0\n";
  }

}
}
