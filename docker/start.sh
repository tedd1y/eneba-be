#!/bin/sh
# Container entrypoint: cache the framework, migrate, then run PHP-FPM and Nginx.
# Caching happens here rather than at build time because Render only provides env variables at runtime.
set -e

cd /var/www/html

php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan migrate --force

# Nginx listens on the port Render provides.
sed "s/__PORT__/${PORT:-10000}/" /etc/nginx/site.conf.template > /etc/nginx/http.d/default.conf

chown -R www-data:www-data storage bootstrap/cache

php-fpm --daemonize
exec nginx -g 'daemon off;'
