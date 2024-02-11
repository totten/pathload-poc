#!/bin/bash

EXAMPLES=$PWD
DIST=$PWD/dist
MAIN=$(dirname "$EXAMPLES")

function PHAR() {
 php -d phar.readonly=0 `which phar` "$@"
}

set -ex

if [ ! -d "$DIST" ]; then
  mkdir "$DIST"
fi

cp "$MAIN/dist/pathload-latest.php" "$EXAMPLES/dist/pathload.php"

for pkg in 'corelib@1.0.0' 'corelib@1.2.3' 'extralib@1.0.0' ; do 
  pushd "lib/$pkg"
    [ -f "$DIST/$pkg.phar" ] && rm -f "$DIST/$pkg.phar" || true
    find -name '*~' -delete
    PHAR pack -f "$DIST/$pkg.phar" -s "../empty-stub.php" .
  popd
done
