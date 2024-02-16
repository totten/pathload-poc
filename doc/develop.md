# Development

## Testing

Tests are written in PHPUnit. They rely on the various examples in `example/lib/`. Generally, the
flow of a unit-test is like this:

```php
function testSomething() {
  // Make a new `lib/` by picking some examples.
  $libDir = buildLibDir(...pick examples...);

  // Activate the polyfill
  ($GLOBALS['_PathLoad']['top'] ?? require currentPolyfill());

  // Configuration
  pathload()->addSearchDir($libDir)->addPackage(...);

  // Usage
  $this->expectOutputLines(['...some text...']);
  \Call\My\Library::code();
}
```

A typical way to run all unit-tests is:

```php
cd pathload/
./scripts/compile.php
phpunit9 --process-isolation
```

> __TIP__: You can test an IDE like PhpStorm. If you need to frequently
> iterate and/or set break-points, look at `currentPolyfill()` and decide
> which option feels better. However, in my experience, it's best to
> only run one test-function at a time.

## Polyfill Versioning

PathLoad is designed to work as a *polyfill* -- you can copy the main file into existing applications and modules.
Of course, several other applications or modules may do the same thing. What happens when two parties on
the same system both load the polyfill... and they specify *different versions*?

New polyfills replace old polyfills. For example:

```php
// Module A loads polyfill version 0
($GLOBALS['_PathLoad'][0] ?? require __DIR__ . '/pathload-0.php');
pathload()->addSearchDir('/srv/app/module-a/lib');

// Module B loads polyfill version 1 -- This supercedes v0.
($GLOBALS['_PathLoad'][1] ?? require __DIR__ . '/pathload-1.php');
pathload()->addSearchDir('/srv/app/module-b/lib');
```

Here, v0 starts; then v1 will replace it. When v1 starts, it needs to extract any useful information from v0.

On the flip side, old polyfills defer to new polyfills. Imagine the order reversed:

```php
// Module B loads polyfill version 1
($GLOBALS['_PathLoad'][1] ?? require __DIR__ . '/pathload-1.php');
pathload()->addSearchDir('/srv/app/module-b/lib');

// Module A loads polyfill version 0 -- This defers to v1. Does nothing.
($GLOBALS['_PathLoad'][0] ?? require __DIR__ . '/pathload-0.php');
pathload()->addSearchDir('/srv/app/module-a/lib');
```

Here, v1 starts; then when someone request v0, no extra work is required. It is the job of v1 to be sufficiently
compatible with v0. (If the contract has changed significantly, then it may provide an adapter specifically for v0 consumers.)

Technically, `$_PathLoad` and `pathload()` provide access to the same data, but they connote different purposes:

* `$GLOBALS['_PathLoad'][nnn]` determines whether a specific version (`nnn`) of the polyfill is available/active.
* `pathload(nnn)` gives a reference to a specific version (`nnn`) of the PathLoad API for you work with
