#!/bin/bash

# بررسی وجود متغیر PORT
if [ -z "$PORT" ]; then
    echo "Error: PORT environment variable is not set. Using default port 10000."
    PORT=10000
fi

echo "Using PORT: $PORT"

# بررسی ماژول‌های لودشده و لاگ کردن
echo "Loaded PHP modules: $(php -m)" > /var/www/html/php_modules.log

# بررسی وجود افزونه PDO_PGSQL
if ! php -m | grep -q pdo_pgsql; then
    echo "Error: PDO_PGSQL extension is not enabled. Please check your PHP configuration."
    exit 1
fi

# بررسی متغیرهای محیطی دیتابیس
if [ -z "$DATABASE_URL" ]; then
    echo "Error: DATABASE_URL environment variable is not set."
    exit 1
fi

echo "DATABASE_URL is set: $DATABASE_URL"

# بررسی دسترسی به فایل‌های اصلی پروژه
if [ ! -f "/var/www/html/webapp.php" ]; then
    echo "Error: webapp.php not found in /var/www/html."
    exit 1
fi

# اجرای سرور PHP
echo "Starting PHP development server on 0.0.0.0:$PORT..."
exec php -S 0.0.0.0:$PORT -t /var/www/html