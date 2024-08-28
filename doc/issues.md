# Issues and Adaptations

This document surveys a series of hypothetical issues/complications that could arise when using Pathload. For each, we seek adaptations to prevent or resolve the issue.

## Glossary

* "Package" - Any unit of distributable software
* "Library" - A kind of package which provides PHP utilities (classes/functions/objects) for developers.
* "Module" ("Application Module") - A kind of package which provides user-visible features; an add-on for a bigger application framework.
  These may have some kind of installation/activation lifecycle.
* "Framework" ("Application Framework") - A bigger application which may be extended by modules.
* "Reloadable" - A package is reloadable if you can make multiple copies and `require` each of them in the same PHP request
   (with later copies replacing earlier copies). Generally, classes/functions with static names are not reloadable,
   but anonymous ones are, and some kinds of metaprogramming may also support reloading.

## Multi-Module Issue/Adaptation

Suppose:

* You have an appilcation-framework with application-modules.
* It does not specifically support Pathload.
* Multiple application-modules include the Pathload polyfill. (The versions are identical or conform to succession protocol.)
* Module A includes `module-a/lib/cloud-file-io@1.0.0`
* Module B includes `module-b/lib/cloud-file-io@1.5.0`

It is important for module A and B to register their `lib/` folders before `cloud-file-io@1` is used.
The general way to do this is:

1. All modules (A and B) should register the pathload metadata early -- during bootstrap / initialization
2. No modules (A or B) should use its libraries during bootstrap / initialization. They must wait until that finishes.

Doing otherwise will may lead to incorrect loading (where version `1.0.0` loads and precludes version `1.5.0`).

> __Speculation__: If integrating support directly into the framework, you might enforce this: disable the autoloader
> at the start of bootstrap (`pathload()->unregister()`) and re-enable at the end (`pathload()->register()`).

If you must provide a library that loads during bootstrap, then it should be *reloadable*. (Patterns discussed later.)

## Multi-Activation Issue/Adaptation

Suppose:

* You have an application-framework with application-modules.
* Pathload is enabled -- either at framework-level or module-level.
* Module A includes `module-a/lib/install-util@1.0.0` which is needed during activation.
* Module B includes `module-b/lib/install-util@1.5.0` which is needed during activation.
* You already ahve the code for modules A and B -- but you need to activate them.

The way in which you activate may affect correctness. In particular, does activatation involve multiple PHP requests? Compare:

* __OK__: Request #1 installs module A (with embedded util v1.0.0). Request #2 installs B (with embedded/newer util v1.5.0).
* __OK__: Request #1 installs module B (with embedded util v1.5.0). Request #2 installs A (whose older v1.0.0 is ignored; v1.5.0 supercedes).
* __Problem__: Request #1 installs module A (with embedded util v1.0.0) then installs module B (whose newer v1.5.0 is blocked).
* __OK__: Request #1 installs module B (with embedded util v1.5.0) then installs module A (whose older v1.0.0 is ignored).

This is a little different from the regular runtime scenario described earlier.  (New module activation may come after
the regular bootstrap, so they don't abide the simple solution from there.)

Some adaptations to prevent the problem scenario:

* Don't use libraries for installation logic.
* Only use reloadable libraries for installation logic.
* Split installation steps across multiple PHP requests.
* Enable all `lib/` folders from all modules (*active or inactive*) before executing any installation logic.

## Multi-Download Issue/Adaptation

This is similar to the Multi-Activation Issue/Adaptation, except in the last assumption.

* You have an application-framework with application-modules.
* Pathload is enabled -- either at framework-level or module-level.
* Module A includes `module-a/lib/install-util@1.0.0` which is needed during activation.
* Module B includes `module-b/lib/install-util@1.5.0` which is needed during activation.
* _You don't have the code for modules A or B -- you need to download and then install them._

Several adaptations for "Multi-Activation" will also address "Multi-Download":

* Don't use libraries for installation logic.
* Only use reloadable libraries for installation logic.
* Split installation steps across multiple PHP requests.

However, if you want the last option ("Enable all `lib/` folders from all modules..."), then you must also incorporate the download
phase into your plan -- download all modules first, then enable all `lib/` folders, then run all installation logic.

## Reloadable Libraries

* By default, PathLoad assumes that packages are *not* reloadable. The package must opt-in to allow itself to be reloaded.

    ```php
    pathload()->activatePackage('reloadable@1', __DIR__, [
      'reloadable' => TRUE,
    ]);
    ```

* In general, reloadable libraries will tend to use anonymous-classes rather than named-classes; and they'll tend
  to use anonymous-functions rather than named-functions. Or, more precisely, they should be identified by
  variable-names rather than static symbols.

    ```php
    // Named function. Non-reloadble.
    function greet(string $name) {
      echo "Hello $name\n";
    }
    greet("world");
    ```
    ```php
    // Variable function. Reloadable.
    $greet = function(string $name) {
      echo "Hello $name\n";
    }
    $greet("world");
    ```
    ```php
    // Named class. Non-reloadable.
    class Greeter {
      public function greet(string $name) {
        echo "Hello $name\n";
      }
    }
    $greeter = new Greeter();
    $greeter->greet("world");
    ```
    ```php
    // Variable class. Reloadable.
    $class = get_class(new class() {
      public function greet(string $name) {
        echo "Hello $name\n";
      }
    });
    $greeter = new $class();
    $greeter->greet("world");
    ```

    In theory, you might have a reloadable library where it defines static facades... as long as the
    static facades defer to dynamic implementations. The signature of the static facade would be

* The PSR-0 and PSR-4 standards map between named-classes and file-paths. In a practical sense, there isn't much point to
  enabling `psr-0` or `psr-4` in a reloadable library -- because named-classes cannot be reloaded. (*In an abstract way,
  you can provide a facade of a named-class/named-function. )

* Class autoloading is a feature of named-classes. Reloadable packages must be loaded explicitly, as in:

  ```php
  pathload()->loadPackage('my-reloadable@1');
  ```
