#!/usr/bin/env sh

set -eu

for writable_directory in \
    storage/logs \
    storage/app/private/kompen-respon-hub/imports \
    storage/app/private/kompen-respon-hub/exports; do
    mkdir -p "$writable_directory"
    chown -R www-data:www-data "$writable_directory"
done

if [ "$#" -gt 0 ] && [ "$1" = "php" ]; then
    exec gosu www-data "$@"
fi

exec "$@"
