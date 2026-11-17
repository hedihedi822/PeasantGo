# syntax=docker/dockerfile:1
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip \
 && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public APP_ENV=prod
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN mkdir -p var public/uploads && chown -R www-data:www-data var public/uploads
EXPOSE 80
