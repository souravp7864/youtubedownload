#!/bin/bash

# Create necessary directories
mkdir -p /var/www/html/data/downloads
chmod -R 777 /var/www/html/data

# Check if BOT_TOKEN is set and not empty
if [ -z "$BOT_TOKEN" ]; then
    echo "❌ ERROR: BOT_TOKEN environment variable is not set"
    exit 1
fi

# Check if it's the example token (basic check)
if [ "$BOT_TOKEN" = "8507471476:AAHkLlfP4uZ8DwNsoffhDPQsfh61QoX9aZc" ]; then
    echo "⚠️ WARNING: Using example BOT_TOKEN - please set your actual bot token"
    echo "⚠️ Continuing with example token for testing..."
fi

echo "✅ Starting Telegram Bot with token: ${BOT_TOKEN:0:10}..."

# Start the Telegram bot in background
cd /var/www/html
php bot.php &

echo "🌐 Starting Apache Web Server..."

# Start Apache in foreground
exec apache2-foreground