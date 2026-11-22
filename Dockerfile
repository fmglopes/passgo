# PASS GO — PHP 8 + Apache + PostgreSQL driver (runs on Render's free web service)
FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*

ENV TZ=Europe/Lisbon
WORKDIR /var/www/html
COPY . /var/www/html

# Only public/ is exposed; src/, config/, database/ stay outside the web root.
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && cp docker/apache-passgo.conf /etc/apache2/conf-available/passgo.conf \
 && a2enconf passgo \
 && chmod +x docker/entrypoint.sh

EXPOSE 10000
CMD ["docker/entrypoint.sh"]
