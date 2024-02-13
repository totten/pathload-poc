<?php

/**
 * @method PathLoadInterface addSearchDir(string $baseDir)
 * @method PathLoadInterface addPackage(string $package, $namespaces, ?string $baseDir = NULL)
 * @method PathLoadInterface addPackageNamespace(string $package, $namespaces)
 * @method PathLoadInterface import(array $all, string $baseDir = '')
 *
 * When you need resources from a package, call loadPackage(). This locates the
 * relevant files and loads them. In general, this shouldn't be necessary because
 * packages are autoloaded.
 *
 * @method PathLoadInterface loadPackage(string $package)
 *
 * The activatePackage() method is for package-implementers. If you are distributing as
 * a singular PHP file (`cloud-io@1.0.0.php`), then you cannot use `pathload.json`.
 * instead, call this method.
 *
 * @method PathLoadInterface activatePackage(string $package, string $dir, array $config)
 */
interface PathLoadInterface {

  // Use soft type-hints. If the contract changes, we won't be able to
  // un-publish or block old implementations, and they need to coexist.
  // This will give us wiggle-room while also giving type-hints in average case.

}
