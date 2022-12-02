#!/usr/bin/env bash

for name in hello-{a,b,c,d,e}.php ; do 
  echo "[[ Run $name ]]"
  echo
  php "$name"
  echo
done