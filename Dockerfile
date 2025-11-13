FROM php:8.2-apache

# Install system dependencies including Node.js for JavaScript runtime
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
    python3-minimal \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mbstring exif pcntl bcmath gd zip

# Set ServerName to fix Apache warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Enable Apache modules including headers
RUN a2enmod rewrite headers

# Install yt-dlp as standalone binary
RUN wget https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp -O /usr/local/bin/yt-dlp && \
    chmod a+rx /usr/local/bin/yt-dlp

# Install JavaScript runtime for yt-dlp
RUN npm install -g mujs

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