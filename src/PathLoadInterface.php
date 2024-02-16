<?php

/**
 * The PathLoad interface is defined via soft signatures ("duck-typing") rather than hard signatures.
 * This matters when multiple parties inject PathLoad support onto a pre-existing framework.
 * In the event of future language changes or contract changes, the soft signatures
 * give wiggle-room to address interoperability/conversion.
 *
 * ==== PACKAGE CONSUMER APIS ===
 *
 * (PathLoad v0) Enable autoloading of `*.phar`, `*.php`, and folders from a search directory.
 *
 * @method PathLoadInterface addSearchDir(string $baseDir)
 *
 * (Pathload v0) Enable autoloading of a specific `*.phar`, `*.php`, or folder.
 * Useful for non-standard file-layout.
 *
 * @method PathLoadInterface addSearchItem(string $name, string $version, string $file, ?string $type = NULL)
 *
 * (PathLoad v0) Declare knowledge about what packages are available. These provide
 * hints for autoloading.
 *
 * The third argument, `$baseDir`, is experimental
 *
 * @method PathLoadInterface addPackage(string $package, $namespaces, ?string $baseDir = NULL)
 *
 * (Pathload v0) When you need resources from a package, call loadPackage().
 * This locates the relevant files and loads them.
 * If you use namespace-autoloading, then this shouldn't be necessary.
 *
 * @method PathLoadInterface loadPackage(string $majorName)
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
