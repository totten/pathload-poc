<?php

namespace PathLoad\Vn;

class Scanner {

  /**
   * @var array
   *   Array(string $id => [package => string, glob => string])
   * @internal
   */
  public $allRules = [];

  /**
   * @var array
   *   Array(string $id => [package => string, glob => string])
   * @internal
   */
  public $newRules = [];

  //internal// /**
  //internal// * @var array
  //internal// *   Array(string $id => [package => string, glob => string])
  //internal// * @internal
  //internal// */
  //internal//public $oldRules = [];

  /**
   * @param array $rule
   *   Ex: ['package' => '*', 'glob' => '/var/www/lib/*@*']
   *   Ex: ['package' => 'cloud-file-io@1', 'glob' => '/var/www/lib/cloud-io@1*.phar'])
   * @return void
   */
  public function addRule(array $rule): void {
    //internal// In prior iterations, this deduped with a guard on `$this->oldRules`. Don't see the point now.
    $id = static::id($rule);
    $this->allRules[$id] = $rule;
    $this->newRules[$id] = $rule;
  }

  public function reset(): void {
    $this->newRules = $this->allRules;
    $this->oldRules = [];
  }

  /**
   * @param string $packageHint
   *   Give a hint about what package we're looking for.
   *   The scanner will try to target packages based on this hint.
   *   Ex: '*' or 'cloud-file-io'
   * @return \Generator
   *   A list of packages. Thesemay not be the exact package you're looking for.
   *   You should assimilate knowledge of all outputs because you may not get them again.
   */
  public function scan(string $packageHint): \Generator {
    yield from [];
    foreach (array_keys($this->newRules) as $id) {
      $searchRule = $this->newRules[$id];
      if ($searchRule['package'] === '*' || $searchRule['package'] === $packageHint) {
        //internal// $this->oldRules[$id] = $searchRule;
        unset($this->newRules[$id]);
        if (isset($searchRule['glob'])) {
          foreach ((array) glob($searchRule['glob']) as $file) {
            if (($package = Package::create($file)) !== NULL) {
              yield $package;
            }
          }
        }
      }
    }
  }

  protected static function id(array $searchRule): string {
    if (isset($searchRule['glob'])) {
      return $searchRule['glob'];
    }
    else {
      throw new \RuntimeException("Cannot identify rule: " . json_encode($searchRule));
    }
  }

}
