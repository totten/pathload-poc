<?php

/**
 * The PathLoad interface is defined via soft signatures rather than hard signatures.
 * In the event of future language changes or contract changes. This matters
 * when multiple parties inject PathLoad support onto a pre-existing framework.
 * The soft signatures give wiggle-room to address interoperability/conversin.
 *
 * ==== PACKAGE CONSUMER APIS ===
 *
 * (PathLoad v0) Enable autoloading of `*.phar`, `*.php`, and folders from a search directory.
 *
 * @method PathLoadInterface addSearchDir(string $baseDir)
 *
 * (PathLoad v0) Declare knowledge about what packages are available. These provide
 * hints for autoloading.
 *
 * @method PathLoadInterface addPackage(string $package, $namespaces, ?string $baseDir = NULL)
 * @method PathLoadInterface addPackageNamespace(string $package, $namespaces)
 *
 * (PathLoad v0, experimental)
 *
 * @method PathLoadInterface import(array $all, string $baseDir = '')
 *
 * (Pathload v0) When you need resources from a package, call loadPackage().
 * This locates the relevant files and loads them.
 * If you use namespace-autoloading, then this shouldn't be necessary.
 *
 * @method PathLoadInterface loadPackage(string $package)
 *
 * ==== PACKAGE PROVIDER APIS ====
 *
 * (PathLoad v0) Activate your package. This allows you to add metadata about activating
 * your own package. In particular, this may be necessary if you have transitive
 * dependencies. This would be appropriate for single-file PHP package (`cloud-io@1.0.0.php`)
 * which lack direct support for `pathload.json`.
 *
 * @method PathLoadInterface activatePackage(string $package, string $dir, array $config)
 */
interface PathLoadInterface {

}
