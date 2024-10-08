# PHP PathLoad (Proof of concept)

This is a test-bed to examine an alternative mechanism for loading dependencies. It is loosely inspired by the handling of versioned libraries in C and Java but tailored to the environment of PHP application-modules (*WordPress plugins, Drupal 7 modules, Backdrop modules, etc*) and PHP libraries (*string-utilities, file-formatters, network-clients, etc*).

Libraries are loaded from a _search-path_ (`PHP_PATHLOAD`) with priority based on _version-number_. For example, the *search-path* might list three folders:

* `/var/www/app/addon-1/lib/`
* `/var/www/app/addon-2/lib/`
* `/usr/local/share/php-updates/`

Each folder contains versioned libraries (eg `cloud-file-io@1.2.3`). Libraries may be plain PHP files, PHAR archives, or subdirectories.

* `/var/www/app/addon-1/lib/`
    * `cloud-file-io@1.1.0.php` (*plain file*)
    * `yaml-util@1.0.0.php` (*plain file*)
* `/var/www/app/addon-2/lib/`
    * `console-lib@2.0.0.phar` (*PHAR*)
    * `cloud-file-io@1.2.3.phar` (*PHAR*)
* `/usr/local/share/php-updates/`
    * `yaml-util@1.0.5` (*subdirectory*)

The challenge for these PHP application-modules is that they must load one version of any library, and their deployment tools (`wget`, `svn`, `git`, `drush dl`, etc) do not reconcile library versions. PathLoad resolves versions by applying [Semantic Versioning](https://semver.org/). `MINOR` and `PATCH` increments must be backward-compatible. `MAJOR` increments are treated as separate packages (*loaded concurrently*).

PathLoad is a protocol where multiple parties (*modules; frameworks; operating systems*) may independently distribute the same libraries -- with old versions yielding to newer replacements. It can be retrofitted into existing platforms. Multiple module-developers may ship the same libraries. Site-builders and security-tools may deploy updated libraries without modifying the application-modules. You simply copy an updated library onto the search-path.

The project is presented as proof-of-concept. Development is heavily test based, and APIs should do what they say; but it's still novel terrain. Some areas for further consideration include:

* [Issues and adaptations](doc/issues.md)
* Benchmarking and optimization
* Build and distribution of library archives (esp [adapting](https://github.com/humbug/php-scoper) existing libraries to support `MAJOR` version coexistence)

## Usage (Application/Module Developer)

Suppose you are developing an application or application-module (WP/D7-style) that requires a library called `cloud-file-io` (`cloud-file-io@1.2.3`). Here's how to use it:

1. Download `pathload-0.php` and `cloud-file-io@1.2.3.phar` into your library folder (`$MY_MODULE/lib/`).

    ```bash
   mkdir $MY_MODULE/lib/
   cd $MY_MODULE/lib/
   wget https://example.com/download/pathload-0.php.txt -O pathload-0.php
   wget https://example.com/download/cloud-file-io@1.2.3.phar -O cloud-file-io@1.2.3.phar
    ```

2. In your applicatin or application-module:

    ```php
    // Enable the pathload polyfill.
    ($GLOBALS['_PathLoad'][0] ?? require __DIR__ . '/lib/pathload-0.php');

    // Register your `lib/` folder:
    pathload()->addSearchDir(__DIR__ . '/lib');

    // Declare an autoloading rule for `cloud-file-io` v1.x
    pathload()->addNamespace('cloud-file-io@1', 'CloudFileIO\\');
    ```

3. Now, you may reference classes like `\CloudFileIO\Amazon\S3` or `\CloudFileIO\Google\Storage`.
4. Pathload integrates into the autoloader. When you actually use `\CloudFileIO\Amazon\S3`, it observes the namespace and loads `cloud-file-io@*.phar`. This will use the best-available version:
    * If your module is the only one to include `cloud-file-io` (specifically `cloud-file-io@1.2.3.phar`), then it will load your version.
    * If another module includes a newer version (`cloud-file-io@1.5.0.phar`), then that will be loaded instead.
    * If another module includes an older version (`cloud-file-io@1.0.0.phar`), then that will be ignored.
    * The choice of "best available version" abides SemVer and its compatibility rules -- version 1.5.0 can automatically replace 1.2.3 and 1.0.0. But 2.0.0 may not automatically replace 1.5.0.

## Usage (Administrator)

If you are managing a system with many modules, then you may wish to deploy your own updates (without directly editing
the upstream modules). Here's how:

1. Create a new folder (eg `/usr/local/share/php-pathload`) and add your new `cloud-file-io@1.8.0.phar`:

  ```bash
   mkdir /usr/local/share/php-pathload
   cd /usr/local/share/php-pathload
   wget https://example.com/download/cloud-file-io@1.8.0.phar -O cloud-file-io@1.8.0.phar
  ```

2. Add a new environment variable (`PHP_PATHLOAD`) to the environment of your PHP application:

  ```bash
  PHP_PATHLOAD=/usr/local/share/php-pathload:/usr/share/php-pathload
  ```

## Usage (Library Distributor)

Let's consider an example library, `cloud-file-io@1.2.3`.  When packaging for distribution, you could provide
this library in any of these formats:

* __PHP Source File__ (`cloud-file-io@1.2.3.php`): This is just a plain old PHP file with some `function`s or `class`es.
* __PHP Archive File__ (`cloud-file-io@1.2.3.phar`): A collection of many PHP files. It should define `pathload.main.php` or `pathload.json`.
* __Local Directory__ (`cloud-file-io@1.2.3/`): A collection of many PHP files. It should define `pathload.main.php` or `pathload.json`.

A single PHP file may be sufficient for a small library. As you get larger content or more classes, it becomes advantageous to use a
PHAR or directory. These should be organized per PSR-0 or PSR-4. To describe the structure more precisely,
include the file `pathload.main.php` or `pathload.json`. These examples are equivalent:

```php
// pathload.main.php
pathload()->activatePackage('my-library@1', __DIR__, [
  'autoload' => [
    'psr-4' => [
      'My\\Php\\Namespace\\' => ['src/'],
    ],
  ]
]);
```
```javascript
// pathload.json
{
  "autoload": {
    "psr-4": {
      "My\\Php\\Namespace\\": ["src/"]
    }
  }
}
```

There are several more examples in [example/lib/](./example/lib). Each example starts
out as a folder and is [compiled](./example/build.sh) to equivalent PHAR+PHP options. These
examples are used extensively in the PathLoad test-suite to ensure that they are well formed.

## More information

* [develop.md](doc/develop.md) talks more about doing development of Pathload codebase
* [discuss.md](doc/discuss.md) has additional notes and thoughts. It should probably be organized more...
