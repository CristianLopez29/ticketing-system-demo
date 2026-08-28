#!/bin/sh
set -e

# Config must be cached before anything else runs: without it every request re-parses
# the whole config/ tree, and route:cache below depends on it being current.
if [ "$1" = "php-fpm" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
fi

exec "$@"
