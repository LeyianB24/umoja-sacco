FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libmariadb-dev mariadb-client git curl zip unzip \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql gd zip \
    && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork rewrite

WORKDIR /var/www/html
COPY . .

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

RUN rm -rf vendor && \
    sed -i '/vendor\/fpdf\/fpdf\.php/d' composer.json && \
    composer install --no-dev --optimize-autoloader

RUN mkdir -p storage/cache storage/logs uploads \
    && chmod 755 storage/cache storage/logs uploads

ENV APP_ENV=production
EXPOSE 80
CMD ["apache2-foreground"]