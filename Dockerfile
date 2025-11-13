FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    python3 \
    python3-pip \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Python dependencies for yt-dlp
RUN pip3 install yt-dlp

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

# Create startup script
COPY start.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/start.sh

# Create data directory for persistent storage
VOLUME /var/www/html/data

CMD ["/usr/local/bin/start.sh"]