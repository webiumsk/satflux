#!/bin/sh
set -e
# Ensure storage and bootstrap/cache are writable by www-data (PHP-FPM runs as www-data).
# Needed when the app is mounted from the host and has different ownership.
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache 2>/dev/null || true
exec php-fpm
