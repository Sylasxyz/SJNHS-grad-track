FROM php:8.2-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    libcurl4-openssl-dev \
    libpng-dev libjpeg-dev libwebp-dev \
 && docker-php-ext-install curl \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p uploads/announcements logs \
 && chmod -R 775 uploads logs

COPY docker/start-php.sh /usr/local/bin/start-php.sh
RUN chmod +x /usr/local/bin/start-php.sh

EXPOSE 8080

CMD ["/usr/local/bin/start-php.sh"]
