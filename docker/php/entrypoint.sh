#!/bin/sh
set -e

# storage/ and bootstrap/cache sit on the Windows bind mount and arrive owned by
# root, which php-fpm's www-data workers cannot write. Write permission alone is
# not enough: Laravel's view compiler calls touch() with an explicit mtime, and
# setting the mtime of a file you do not own fails with EPERM. Both chown and
# chmod persist on this mount, so take ownership outright.
for d in storage bootstrap/cache; do
    if [ -d "/var/www/html/$d" ]; then
        chown -R www-data:www-data "/var/www/html/$d" 2>/dev/null || true
        chmod -R u+rwX,g+rwX "/var/www/html/$d" 2>/dev/null || true
    fi
done

exec "$@"
