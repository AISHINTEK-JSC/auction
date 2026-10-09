# syntax=docker/dockerfile:1.7

# PHP-FPM + composer + node in one image: Wayfinder's Vite plugin runs `php artisan`,
# so node and php have to live together.
FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql pcntl bcmath intl gd exif zip opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g www-data www-data \
    && mkdir -p /var/www/.npm /var/www/.composer && chown -R www-data:www-data /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html
ENV COMPOSER_HOME=/var/www/.composer

USER www-data
CMD ["php-fpm"]
