#!/bin/bash

# Create necessary directories
mkdir -p /var/www/html/data/downloads
chmod -R 777 /var/www/html/data

# Check if BOT_TOKEN is set
if [ -z "$BOT_TOKEN" ]; then
    echo "❌ ERROR: BOT_TOKEN environment variable is not set"
    exit 1
fi

echo "🔧 Checking dependencies..."
# Check if yt-dlp is available
if ! command -v yt-dlp &> /dev/null; then
    echo "❌ yt-dlp not found"
    exit 1
fi

echo "✅ yt-dlp version: $(yt-dlp --version)"

echo "🤖 Starting Telegram Bot with token: ${BOT_TOKEN:0:10}..."

# Start the Telegram bot in background
cd /var/www/html
php bot.php &

echo "🌐 Starting Apache Web Server..."

# Start Apache in foreground
exec apache2-foreground