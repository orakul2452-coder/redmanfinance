#!/bin/sh
set -eu

port="${PORT:-10000}"
mkdir -p /var/www/html/account/uploads /var/www/html/admin/c2wad/uploads /var/www/html/admin/c2wad/logo
chown -R www-data:www-data /var/www/html/account/uploads /var/www/html/admin/c2wad/uploads /var/www/html/admin/c2wad/logo

sed -ri "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground