FROM php:8.2-apache

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libmysqlclient-dev \
    mariadb-client \
    git \
    curl \
    zip \
    unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set up Apache document root
WORKDIR /var/www/html
COPY . .

# Copy Apache configuration
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Create required directories
RUN mkdir -p storage/cache storage/logs uploads && chmod 755 storage/cache storage/logs uploads

# Set environment
ENV APP_ENV=production

EXPOSE 80

CMD ["apache2-foreground"]
