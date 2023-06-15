# PathLoad PHP (Proof of concept)

This is a test-bed to examine an alternative mechanism for loading dependencies. It is loosely inspired by the handling of versioned libraries in C and Java but tailored to the environment of PHP application-modules (*in the sense of WordPress plugins, Drupal 7 modules, Backdrop modules, etc*).

Classes are loaded from a _search-path_ with priority based on _version number_. For example, the *search-path* might list 3 directories. Each directory has versioned PHAR libraries.

* `/var/www/app/addon-1/dist/`
    * `console-lib@2.0.0.phar`
    * `cloud-file-io@1.2.3.phar`
* `/var/www/app/addon-2/dist/`
    * `cloud-file@1.1.0.phar`
    * `yaml-util@1.0.0.phar`
* `/usr/local/share/php-updates/`
    * `yaml-util@1.0.5.phar`

The challenge for these PHP application-modules is that they must load one version of any library, and their deployment tools (`wget`, `svn`, `git`, `drush dl`, etc) do not reconcile library versions.

PathLoad is a protocol where each addon may independently distribute its preferred libraries -- but old libraries will yield to new replacements. It works even if the host application (*WP, D7*) lacks support, and it allows multiple developers to ship the same library. It also means that site-builders and security-tools may deploy updated libraries without modifying the application-modules -- you simply copy an updated library onto the search-path.

## Usage

Suppose you are developing an application-module for WP/D7 that requires a library called `cloud-file-io`. Here's how to use it:

1. Download `cloud-file-io` as a PHAR file (eg `cloud-file-io@1.2.3.phar`)
2. Copy `cloud-file-io@1.2.3.phar` and `pathload.php` into your codebase (`$MY_MODULE/dist/`)
3. In your application-module, add 2 lines of code to activate the PHAR:

    ```php
    // Add your `dist/` folder to PathLoad:
    ($GLOBALS['_PathLoad'] ?? require __DIR__ . '/dist/pathload.php')->append(__DIR__ . '/dist');
 
    // Declare that you wish to use `cloud-file-io` v1.x
    pathload()->addPackage('CloudFileIO\\', 'cloud-file-io@1');
    ```

3. Now, anywhere in your plugin, you may reference classes like `\CloudFileIO\Amazon\S3` or `\CloudFileIO\Google\Storage`.
4. At runtime, when using `\CloudFileIO\Amazon\S3`, it will find the best-available version of `cloud-file-io.phar`.
    * If your plugin is the only one to include `cloud-file-io` (specifically `cloud-file-io@1.2.3.phar`), then it will load your version.
    * If another plugin includes a newer version (`cloud-file-io@1.5.0.phar`), then that will be loaded instead.
    * If another plugin includes an older version (`cloud-file-io@1.0.0.phar`), then that will be ignored.
    * The choice of "best available version" abides SemVer and its compatibility rules -- version 1.5.0 can automatically replace 1.2.3 and 1.0.0. But 2.0.0 may not automatically replace 1.5.0.

## Important notes

* The `pathload.php` adapter (aka `$_PathLoad` aka `pathload()`) is only initialized one time.
* The pathload object allows you to register metadata. This should be done very early (*before classes are actually needed*).
* It integrates into the classloader - it will not load anything until there is a live requirement for a specific class.
    * Ex: ___If___ someone instantiates a class from the namespace `CloudFileIO\`, ___then___ it will use `cloud-file-io@1.2.3.phar`. ___Otherwise___, the PHAR file is ignored.
* There is an empirical question about the performance of this mechanism.
    * That's why we need a proof-of-concept.
    * Maybe performance is good. The PHP interpreter is pretty fast these days. Potentially-expensive steps (like `glob()` or `require_once`) only run if you actually need them.
    * Performance is probably not as good as composer, esp compared to classmap optimization.
    * It might be improved with a bit of caching/scanning. However, the caching mechanism depends on the local environment. I would probably organize this as an environment-specific optimization. (Ex: Create "pathload-wp" plugin to optimize within WP environment; create "pathload_d7" module to optimize within D7 environment. These would be optional things to squeeze a few more milliseconds out of each pageview.)

## Example project

There is a small [example project](./example) in this repo. You can see it in action as:

```bash
cd example

## Build the `dist/*.phar` files.
./build.sh

## Run the examples
./run-all.sh
```

## Library packaging

Let's consider our example library, `cloud-file-io@1.2.3`. The emphasis here has been placed on PHAR packages. However, PathLoad POC can read libraries in a few formats:

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