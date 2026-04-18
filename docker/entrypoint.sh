#!/bin/sh
set -e

if [ ! -f "composer.json" ]; then
    echo "Initializing Symfony project..."
    composer create-project symfony/skeleton:"7.*" tmp_dir --no-interaction
    cp -R tmp_dir/. .
    rm -rf tmp_dir
    
    # Ensure our tokens exist in the created .env
    echo "" >> .env
    echo "TELEGRAM_TOKEN=your_telegram_token_here" >> .env
    echo "GEMINI_API_KEY=your_gemini_api_key_here" >> .env
fi

echo "Installing dependencies..."
composer install --no-interaction --optimize-autoloader

# We need to make sure the directories exist for the socket/log, etc.
mkdir -p /run/nginx

exec "$@"
