FROM php:8.2-apache

# Install required PHP extensions + GD dependencies
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

# Install Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set up Apache document root
WORKDIR /var/www/html
COPY . .

# Copy Apache configuration
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Patch composer.json to remove broken fpdf classmap entry, then install
RUN rm -rf vendor && \
    sed -i '/vendor\/fpdf\/fpdf\.php/d' composer.json && \
    composer install --no-dev --optimize-autoloader

# Create required directories
RUN mkdir -p storage/cache storage/logs uploads && chmod 755 storage/cache storage/logs uploads

# Set environment
ENV APP_ENV=production

EXPOSE 80

CMD ["apache2-foreground"]