#!/bin/sh
set -e
PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
a2enmod headers >/dev/null

if [ -n "$DATABASE_URL" ] || [ -n "$PASSGO_DB_HOST" ]; then
  php bin/setup_db.php || echo "WARNING: database setup failed; check DATABASE_URL"
fi
