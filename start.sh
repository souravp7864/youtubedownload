#!/bin/bash

# Start Apache in the background
apache2-foreground &

# Wait for Apache to start
sleep 5

# Start the Telegram bot
php /var/www/html/bot.php

# Keep container running
wait