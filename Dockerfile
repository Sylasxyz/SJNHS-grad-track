FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# mod_php requires prefork; avoid "More than one MPM loaded" if multiple MPM symlinks exist
RUN set -eux; \
    for m in mpm_event mpm_worker; do \
      if [ -e "/etc/apache2/mods-enabled/${m}.load" ]; then a2dismod "${m}"; fi; \
    done; \
    a2enmod mpm_prefork; \
    apache2ctl configtest

COPY docker/apache-env.conf /etc/apache2/conf-available/app-env.conf
RUN a2enconf app-env

ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p uploads/announcements logs \
 && chown -R www-data:www-data uploads logs \
 && chmod -R 775 uploads logs

COPY docker/start-apache.sh /usr/local/bin/start-apache.sh
RUN chmod +x /usr/local/bin/start-apache.sh

EXPOSE 8080

CMD ["/usr/local/bin/start-apache.sh"]
