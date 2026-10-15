FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    unzip \
    libcurl4-openssl-dev \
 && docker-php-ext-install curl pdo_mysql \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first so they are cached between deploys.
COPY laravel/composer.json laravel/composer.lock laravel/
RUN cd laravel && composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . /var/www/html/

# uploads/ sits next to laravel/ (config/school.php uploads_root); on Railway it is a volume.
RUN cd laravel \
 && composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    ../uploads/announcements ../uploads/profiles \
 && chmod -R 775 storage bootstrap/cache ../uploads

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_TIMEZONE=Asia/Manila \
    LOG_CHANNEL=stderr \
    SESSION_SECURE_COOKIE=true \
    PHP_CLI_SERVER_WORKERS=4

COPY docker/start-laravel.sh /usr/local/bin/start-laravel.sh
RUN chmod +x /usr/local/bin/start-laravel.sh

EXPOSE 8080

CMD ["/usr/local/bin/start-laravel.sh"]
