# PathLoad

This is a proof-of-concept/test-bed to examine an alternative mechanism for loading dependencies.  In this POC, classes are loaded from a
_search-path_ with priority given based on _version number_.  The mechanism may be better suited for plugin/module development in platforms
like WordPress, Drupal 7, and Backdrop -- platforms where `composer` is not canonical, and where site-builders may add new plugins by
simply dropping them into the file-tree.

## Usage (General Concept)

Suppose you are developing a plugin/module that requires a library called `cloud-file-io`. To use it, you would:

1. In your plugin source-tree, add a folder `./dist/` for distributable libraries, such as:
    * `./dist/cloud-file-io@1.2.3.phar` is a copy of the `cloud-file-io` (named for its specific version `1.2.3`)
    * `./dist/pathload.php` is a polyfill that adds support for the PathLoad API.
2. The plugin/module has a mainfile. In this file, you should:
    * Activate PathLoad. Add `./dist/` to the search-path.
        ```php
        ($GLOBALS['_PathLoad'] ?? require __DIR__ . '/dist/pathload.php')->append(__DIR__ . '/dist');
        ```
    * Declare that your module uses some classes from `cloud-file-io` v1.x:
        ```php
        pathload()->addPackage('CloudFileIO\\', 'cloud-file-io@1');
        ```
3. Now, anywhere in your plugin, you may reference classes like `\CloudFileIO\Amazon\S3` or `\CloudFileIO\Google\Storage`.
4. At runtime, when using `\CloudFileIO\Amazon\S3`, it will find the best-available version of `cloud-file-io.phar`.
    * If your plugin is the only one to include `cloud-file-io` (specifically `cloud-file-io@1.2.3.phar`), then it will load your version.
    * If another plugin includes a newer version (`cloud-file-io@1.5.0.phar`), then that will be loaded instead.
    * If another plugin includes an older version (`cloud-file-io@1.0.0.phar`), then that will be ignored.
    * The choice of "best available version" abides SemVer and its compatibility rules -- version 1.5.0 can automatically replace 1.2.3 and 1.0.0.
      But 2.0.0 may not automatically replace 1.5.0.

## Usage (Example)

There is a small example project in this repo. You can see it in action as:

```bash
cd example

## Build the `dist/*.phar` files.
./build.sh

## Run the examples
./run-all.sh
```

## File Layout

Suppose you have a library `cloud-file-io@1.2.3`. You may add it in a few ways:

* `./dist/cloud-file-io@1.2.3.php`: PHP source file. Loading this file should provide all the necessary classes (or setup a classloader).
* `./dist/cloud-file-io@1.2.3.phar`: PHP archive file. It should setup a classoader using `.config/pathload.php` and/or `composer.json` (or using a custom "stub").
* `./dist/cloud-file-io@1.2.3`: Local directory. It should setup a classloader using `.config/pathload.php` and/or `composer.json`.

## Discussion

Topics one might consider for this POC:

* How does it compare to status-quo? To using idealized composer? To PEAR? To `LD_LIBRARY_PATH`?
* What would it look like to have a site-wide "package update" process (e.g. deploying security updates)?
* What would be involved with loading existing/public libraries? How important is it to do?
* How significant is the performance impact if every pageload performs a `glob("*@*")` on each `$PLUGIN/dist`? What's the overhead of reading from PHARs?

## Sketch: Composer Bridge

In theory, you could write a script to generate PHAR from `composer.json` metadata, eg

```bash
buildphar -i https://github.com/thephpleague/csv/archive/refs/tags/9.8.0.zip \
   -o thephpleague-csv@9.8.0.phar
```

In simplest form, the `buildphar` would simply read `composer.json` and add some related metadata.

But there are considerations:

* Major-Version Conflict: Under SemVer, major-versions (eg Guzzle 6 + Guzzle 7) should generally be assumed to be conflicted.  Composer
  precludes you from loading conflicted versions -- it simply refuses to install them.  If you have a search-path where multiple
  people add libraries, then you cannot preclude this so easily.  Instead, it may be better to use [namespace
  prefixing](https://github.com/humbug/php-scoper) -- so `buildphar` would edit the namespace (eg `V6\GuzzleHttp\Guzzle`
  and `V7\GuzzleHttp\Guzzle`). This would allow you to have multiple versions of Guzzle when necessary (6.x vs 7.x), but it would
  dedupe when possible (6.0.0 + 6.1.0 ==> pick 6.1.0). It's unclear how much work would be needed into tuning the namespace-prefix mechanism.

* Micro-Libraries: Some libraries have pretty small scopes (eg `symfony/polyfill-ctype`).  When using `composer`, these microlibraries
  may be used -- but you don't think about them. However, if you took the exact same libraries and managed PHARs, then they would become more
  apparent -- it doesn't seem ideal to have a dozen small PHARs. 

Of course, I'm not even certain if a bridge is needed...
