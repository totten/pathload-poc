#!/bin/bash

## Take the library folders in `./lib`. Conver them to `*.phar` and `*.php` builds.

EXAMPLES=$PWD
IN_DIR="$PWD/lib"
OUT_DIR="$PWD/lib"
MAIN=$(dirname "$EXAMPLES")

function PHAR() {
 php -d phar.readonly=0 `which phar` "$@"
}

set -ex

if [ ! -d "$OUT_DIR" ]; then
  mkdir "$OUT_DIR"
fi

for pkg in 'corelib@1.0.0' 'corelib@1.2.3' 'corelib@1.6.0' 'extralib@1.0.0' 'extralib@1.1.0' ; do
  pushd "$IN_DIR/$pkg"
    [ -f "$OUT_DIR/$pkg.phar" ] && rm -f "$OUT_DIR/$pkg.phar" || true
    find -name '*~' -delete
    PHAR pack -f "$OUT_DIR/$pkg.phar" -s "../empty-stub.php" .
    php $MAIN/scripts/concat-php.php $( find -name '*.php' | grep -v pathload.php ) >"$OUT_DIR/$pkg.php"
  popd
done
