#!/bin/bash

# Create necessary directories
mkdir -p /var/www/html/data/downloads
chmod -R 777 /var/www/html/data

# Check if BOT_TOKEN is set
if [ -z "$BOT_TOKEN" ] || [ "$BOT_TOKEN" = "8507471476:AAHkLlfP4uZ8DwNsoffhDPQsfh61QoX9aZc" ]; then
    echo "❌ ERROR: Please set BOT_TOKEN environment variable"
    echo "❌ Current BOT_TOKEN: $BOT_TOKEN"
    exit 1
fi

echo "✅ Starting Telegram Bot with token: ${BOT_TOKEN:0:10}..."

# Start the Telegram bot in background
cd /var/www/html
php bot.php &

echo "🌐 Starting Apache Web Server..."

# Start Apache in foreground
exec apache2-foreground