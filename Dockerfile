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

# 2. Nuclear MPM fix
RUN rm -f /etc/apache2/mods-enabled/mpm_* \
    && rm -f /etc/apache2/mods-available/mpm_event.load \
    && rm -f /etc/apache2/mods-available/mpm_worker.load \
    && a2enmod mpm_prefork rewrite

# 3. Set up working directory and copy application
WORKDIR /var/www/html
COPY . .

# 4. Write Apache config INLINE — bypasses docker/apache.conf entirely
RUN printf '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html\n\
    DirectoryIndex index.php index.html\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

# 5. Install Composer and project dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf vendor \
    && sed -i '/vendor\/fpdf\/fpdf\.php/d' composer.json \
    && composer install --no-dev --optimize-autoloader

# 6. Set permissions
RUN mkdir -p storage/cache storage/logs uploads \
    && chmod -R 777 storage/cache storage/logs uploads \
    && chown -R www-data:www-data /var/www/html

# 7. Environment
ENV APP_ENV=production
EXPOSE 80
CMD ["apache2-foreground"]