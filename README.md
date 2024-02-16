# PHP PathLoad (Proof of concept)

This is a test-bed to examine an alternative mechanism for loading dependencies. It is loosely inspired by the handling of versioned libraries in C and Java but tailored to the environment of PHP application-modules (*in the sense of WordPress plugins, Drupal 7 modules, Backdrop modules, etc*).

Classes are loaded from a _search-path_ (`PHP_PATHLOAD`) with priority based on _version-number_. For example, the *search-path* might list three locations:

* `/var/www/app/addon-1/lib/`
* `/var/www/app/addon-2/lib/`
* `/usr/local/share/php-updates/`

Each contains versioned libraries (eg `cloud-file-io@1.2.3`). Libraries may be plain PHP files, PHAR archives, or subdirectories.

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

The project is presented as proof-of-concept. Its APIs should work as advertised, and it includes tests. But there are complementary topics for further investigation -- especially:

* Benchmarking and optimization
* Build and distribution of library archives (esp [adaptating](https://github.com/humbug/php-scoper) existing libraries to support `MAJOR` version coexistence)

## Usage (Module Developer)

Suppose you are developing an application-module for WP/D7 that requires a library called `cloud-file-io` (`cloud-file-io@1.2.3`). Here's how to use it:

1. Download `pathload-0.php` and `cloud-file-io@1.2.3.phar` into your library folder (`$MY_MODULE/lib/`).

    ```bash
   mkdir $MY_MODULE/lib/
   cd $MY_MODULE/lib/
   wget https://example.com/download/pathload-0.php.txt -O pathload-0.php
   wget https://example.com/download/cloud-file-io@1.2.3.phar -O cloud-file-io@1.2.3.phar
    ```

2. In your application-module:

    ```php
    // Enable the pathload polyfill.
    ($GLOBALS['_PathLoad'][0] ?? require __DIR__ . '/lib/pathload-0.php');

    // Register your `lib/` folder:
    pathload()->addSearchDir(__DIR__ . '/lib');

    // Declare an autoloading rule for `cloud-file-io` v1.x
    pathload()->addPackage('cloud-file-io@1', 'CloudFileIO\\');
    ```

3. Now, you may reference classes like `\CloudFileIO\Amazon\S3` or `\CloudFileIO\Google\Storage`.
4. Pathload integrates into the autoloader. When you actually use `\CloudFileIO\Amazon\S3`, it observes the namespace and loads `cloud-file-io@*.phar`. This will use the best-available version:
    * If your module is the only one to include `cloud-file-io` (specifically `cloud-file-io@1.2.3.phar`), then it will load your version.
    * If another module includes a newer version (`cloud-file-io@1.5.0.phar`), then that will be loaded instead.
    * If another module includes an older version (`cloud-file-io@1.0.0.phar`), then that will be ignored.
    * The choice of "best available version" abides SemVer and its compatibility rules -- version 1.5.0 can automatically replace 1.2.3 and 1.0.0. But 2.0.0 may not automatically replace 1.5.0.

## Usage (Administrator)

If you are managing a system with many modules, then you may wish deploy your own updates (without directly editing
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
this library in a few formats:

* `cloud-file-io@1.2.3.php`: __PHP source file__: Loading this file should provide all the necessary classes.
* `cloud-file-io@1.2.3.phar`: __PHP archive file: It should setup a classoader using `pathload.main.php` or `pathload.json`.
* `cloud-file-io@1.2.3/`: __Local directory: It should setup a classloader using `pathload.main.php` or `pathload.json`.

The internal structure of a PHAR or directory should abide PSR-0 or PSR-4. The file `pathload.main.php` or
`pathload.json` can be used to describe the structure of the library. These are equivalent:

```php
// pathload.main.php
pathload()->activatePackage('my-library@1', __DIR__, [
  'autoload' => [
    'psr-4' => [
      'My\\Php\\Namespace\\' => 'src/',
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

There are several examples of these formats in [example/lib/](./example). Each example starts
out as a folder and is [compiled](./example/build.sh) to equivalent PHAR+PHP options. These
examples are used extensively in the PathLoad test-suite to ensure that they are well formed.

## More information

The [discuss.md](doc/discuss.md) has additional notes and thoughts. It should probably be organized more...
