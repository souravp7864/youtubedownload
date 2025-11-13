FROM php:8.2-apache

# Install system dependencies including Python
RUN apt-get update && apt-get install -y \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    ffmpeg \
    wget \
    python3 \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mbstring exif pcntl bcmath gd zip

# Set ServerName to fix Apache warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Enable Apache modules including headers
RUN a2enmod rewrite headers

# Install yt-dlp via pip (proper installation)
RUN pip3 install yt-dlp

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Create and set permissions for data directory
RUN mkdir -p /var/www/html/data && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/html/data

# Create startup script
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

CMD ["/usr/local/bin/start.sh"]