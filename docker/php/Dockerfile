FROM dunglas/frankenphp:php8.4-alpine

RUN install-php-extensions \
    pdo_pgsql \
    redis \
    intl \
    bcmath \
    gd \
    zip \
    opcache \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Non-root by default; the compose bind mount keeps ownership sane in dev.
ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 8000

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000"]
