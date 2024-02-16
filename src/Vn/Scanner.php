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

  /**
   * @var array
   *   Array(string $id => [package => string, glob => string])
   * @internal
   */
  public $oldRules = [];

  /**
   * @param array $rule
   *   Ex: ['package' => '*', 'glob' => '/var/www/lib/*@*']
   *   Ex: ['package' => 'cloud-file-io@1', 'glob' => '/var/www/lib/cloud-io@1*.phar'])
   * @return void
   */
  public function addRule(array $rule): void {
    // NOTE: Previous iteration guarded against re-adding old rules. When this iteration
    // stabilizes, we should reasses. Maybe we still need the guard. Maybe we drop it... and
    // then drop $oldRules entirely. Need to bear in mind the "next-extension-install" use-case.
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
        $this->oldRules[$id] = $searchRule;
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
