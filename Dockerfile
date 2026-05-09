FROM php:8.2-apache

# 1. Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    mariadb-client \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql gd zip \
    && rm -rf /var/lib/apt/lists/*

# 2. Fix MPM conflict at image level
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    && rm -f /etc/apache2/mods-enabled/mpm_*.conf \
    && rm -f /etc/apache2/mods-available/mpm_event.load \
    && rm -f /etc/apache2/mods-available/mpm_event.conf \
    && rm -f /etc/apache2/mods-available/mpm_worker.load \
    && rm -f /etc/apache2/mods-available/mpm_worker.conf \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite

# 3. Install Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 4. Set up working directory
WORKDIR /var/www/html
COPY . .

# 5. Copy Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# 6. Install PHP dependencies
RUN rm -rf vendor && \
    sed -i '/vendor\/fpdf\/fpdf\.php/d' composer.json && \
    composer install --no-dev --optimize-autoloader

# 7. Set permissions
RUN mkdir -p storage/cache storage/logs uploads \
    && chmod -R 777 storage/cache storage/logs uploads \
    && chown -R www-data:www-data /var/www/html

# 8. Environment
ENV APP_ENV=production
EXPOSE 80

# 9. Start Apache with explicit MPM check
CMD ["bash", "-c", "rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_worker.load 2>/dev/null; apache2-foreground"]