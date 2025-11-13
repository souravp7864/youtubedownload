#!/bin/bash

# Start the bot in background
echo "🤖 Starting Telegram Bot..."
php /var/www/html/bot.php &

# Start Apache in foreground
echo "🌐 Starting Apache Web Server..."
exec apache2-foreground