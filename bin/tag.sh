#!/usr/bin/env bash

V="$1"
M="$2"

if [ $# -lt 1 ]
then
    echo "Usage: $0 (version number as x.x.x)"
    exit 1
fi;

git tag -a v$V -m "Version $V $M"
git push --tags
sh ../version.sh
composer update
grunt dist
sleep 5
git commit -a -m "bump $V"
git push