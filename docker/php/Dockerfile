FROM php:8.4-fpm

# Composer + the extension installer helper
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# intl is required by Filament; pdo_mysql/gd/zip/bcmath are the usual Laravel set
RUN install-php-extensions \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

# The bind mount arrives as root:root 0755, so the www-data pool cannot write
# storage/ or bootstrap/cache. chmod does persist on this mount, so the
# entrypoint opens those two trees up on every start.
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php/art /usr/local/bin/art
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/art

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
