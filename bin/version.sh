#!/usr/bin/env bash

PLUGIN='../minio-webhook.php'
VERSION=$(echo $(git describe --tags) | sed -n 's/.*\([0-9]\.[0-9]\.[0-9]*\).*/\1/p')

sed -i'' -e 's/\(Version\:*\).*$/\1 '$VERSION'/g' $PLUGIN
rm -f $PLUGIN-e