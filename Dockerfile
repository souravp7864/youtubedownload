FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    ffmpeg \
    wget \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files and install dependencies
COPY composer.json composer.lock* ./
RUN if [ -f composer.lock ]; then composer install --no-dev --no-scripts --no-autoloader; fi

# Copy application files
COPY . .

# Run composer autoloader
RUN if [ -f composer.lock ]; then composer dump-autoload --optimize; fi

# Create and set permissions for data directory
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/html/data

# Configure Apache
RUN a2enmod rewrite
COPY .htaccess .htaccess

CMD ["apache2-foreground"]