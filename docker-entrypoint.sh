#!/bin/sh
set -eu

port="${PORT:-10000}"
storage_root="/var/www/html/storage"

persist_directory() {
	relative_path="$1"
	storage_name="$2"
	live_path="/var/www/html/${relative_path}"
	storage_path="${storage_root}/${storage_name}"

	mkdir -p "$storage_path"
	if [ -d "$live_path" ] && [ ! -L "$live_path" ]; then
		cp -a "${live_path}/." "$storage_path/"
		rm -rf "$live_path"
	fi
	mkdir -p "$(dirname "$live_path")"
	if [ ! -L "$live_path" ]; then
		ln -s "$storage_path" "$live_path"
	fi
	chown -R www-data:www-data "$storage_path"
}

persist_directory "account/uploads" "account-uploads"
persist_directory "admin/c2wad/uploads" "admin-uploads"
persist_directory "admin/c2wad/logo" "admin-logos"

sed -ri "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground