#!/bin/bash

# Create necessary directories
mkdir -p /var/www/html/data/downloads
chmod -R 777 /var/www/html/data

# Check if BOT_TOKEN is set and not empty
if [ -z "$BOT_TOKEN" ]; then
    echo "❌ ERROR: BOT_TOKEN environment variable is not set"
    exit 1
fi

echo "🔧 Checking dependencies..."
# Check if Python and yt-dlp are available
if ! command -v python3 &> /dev/null; then
    echo "❌ Python3 not found"
    exit 1
fi

if ! python3 -c "import yt_dlp" &> /dev/null; then
    echo "❌ yt-dlp not found in Python path"
    exit 1
fi

echo "✅ Dependencies check passed"

echo "🤖 Starting Telegram Bot with token: ${BOT_TOKEN:0:10}..."

# Start the Telegram bot in background
cd /var/www/html
php bot.php &

echo "🌐 Starting Apache Web Server..."

# Start Apache in foreground
exec apache2-foreground