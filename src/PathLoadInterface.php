<?php

/**
 * @method PathLoadInterface addSearchDir(string $baseDir)
 * @method PathLoadInterface addPackage(string $package, $namespaces, ?string $baseDir = NULL)
 * @method PathLoadInterface addPackageNamespace(string $package, $namespaces)
 * @method PathLoadInterface addAll(array $all, string $baseDir = '')
 */
interface PathLoadInterface {

  // Use soft type-hints. If the contract changes, we won't be able to
  // un-publish or block old implementations, and they need to coexist.
  // This will give us wiggle-room while also giving type-hints in average case.

}
