# Discussion

These are generally unorganized notes...

## Details

* The pathload object (aka `pathload.php` aka `$_PathLoad` aka `pathload()`) is only initialized one time
  (in general usage).
* The pathload object allows you to register metadata. This should be done very early (*before classes are actually needed*).
* Once a class is actually used, it *commits to the version*.
* It integrates into the classloader - it will not load anything until there is a live requirement for a specific class.
    * Ex: ___If___ someone instantiates a class from the namespace `CloudFileIO\`, ___then___ it will use `cloud-file-io@1.2.3.phar`. ___Otherwise___, the PHAR file is ignored.
* There is an empirical question about the performance of this mechanism.
    * That's why we need a proof-of-concept.
    * Maybe performance is good. The PHP interpreter is pretty fast these days. Potentially-expensive steps (like `glob()` or `require_once`) only run if you actually need them.
    * Performance is probably not as good as composer, esp compared to classmap optimization.
    * It might be improved with a bit of caching/scanning. However, the caching mechanism depends on the local environment. I would probably organize this as an environment-specific optimization. (Ex: Create "pathload-wp" plugin to optimize within WP environment; create "pathload_d7" module to optimize within D7 environment. These would be optional things to squeeze a few more milliseconds out of each pageview.)
* This resolves conflicts between `MINOR` and `PATCH` versions -- but not `MAJOR` versions. There is more discussion ("Composer Bridge") about ways to tackle that.
* The intention is for `pathload.php` to change very, very rarely. However, it is also a new design, and some initial iteration
  is... not unlikely. This is a challenge because the `pathload.php` is also intended to be copied around. In anticipation,
  it includes a protocol for new versions to supercede old versions. This can be seen the expression:

    ```php
    ($GLOBALS['_PathLoad'][0] ?? require __DIR__ . '/dist/pathload.php');
    ($GLOBALS['_PathLoad'][240201] ?? require __DIR__ . '/dist/pathload.php');
    ($GLOBALS['_PathLoad'][240202] ?? require __DIR__ . '/dist/pathload.php');
    ```

    In this expression, we request an instance of PathLoad that is compliant with v0 (or v240201 or v240202):

    * If it's already available, then no work is required.
    * If it's not available, then it will load your preferred copy of `pathload.php`. This will have
      an opportunity to replace the `_PathLoad` (and convert any metadata).

## Questions

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

Considerations:

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
