#!/usr/bin/env bash
set -e

php scripts/compile.php
(cd example && ./build.sh)
