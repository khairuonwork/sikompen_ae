#!/usr/bin/env sh

set -eu

chown -R www-data:www-data storage/app/private/kompen-respon-hub/imports

if [ "$#" -gt 0 ] && [ "$1" = "php" ]; then
    exec gosu www-data "$@"
fi

exec "$@"
