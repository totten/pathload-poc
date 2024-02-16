<?php

namespace PathLoad\Vn;

class Package {

  /**
   * Split a package identifier into its parts.
   *
   * @param string $package
   *   Ex: 'foobar@1.2.3'
   * @return array
   *   Tuple: [$majorName, $name, $version]
   *   Ex: 'foobar@1', 'foobar', '1.2.3'
   */
  public static function parseExpr(string $package): array {
    if (strpos($package, '@') === FALSE) {
      throw new \RuntimeException("Malformed package name: $package");
    }
    [$prefix, $suffix] = explode('@', $package, 2);
    $prefix = str_replace('/', '~', $prefix);
    [$major] = explode('.', $suffix, 2);
    return ["$prefix@$major", $prefix, $suffix];
  }

  /**
   * @param string $file
   *  Ex: '/var/www/app-1/lib/foobar@.1.2.3.phar'
   * @return \PathLoad\Vn\Package|null
   */
  public static function create(string $file): ?Package {
    if (substr($file, -4) === '.php') {
      $base = substr(basename($file), 0, -4);
      $type = 'php';
    }
    elseif (substr($file, '-5') === '.phar') {
      $base = substr(basename($file), 0, -5);
      $type = 'phar';
    }
    elseif (is_dir($file)) {
      $base = basename($file);
      $type = 'dir';
    }
    else {
      return NULL;
    }

    $self = new Package();
    [$self->majorName, $self->name, $self->version] = static::parseExpr($base);
    $self->file = $file;
    $self->type = $type;
    return $self;
  }

  /**
   * @var string
   *   Ex: '/var/www/app-1/lib/cloud-file-io@1.2.3.phar'
   */
  public $file;

  /**
   * @var string
   *   Ex: 'cloud-file-io'
   */
  public $name;

  /**
   * @var string
   *   Ex: 'cloud-file-io@1'
   */
  public $majorName;

  /**
   * @var string
   *   Ex: '1.2.3'
   */
  public $version;

  /**
   * @var string
   *   Ex: 'php' or 'phar' or 'dir'
   */
  public $type;

}
