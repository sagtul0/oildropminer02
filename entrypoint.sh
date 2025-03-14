#!/bin/bash
echo "Using PORT: $PORT"
# بررسی متغیرهای جداگانه دیتابیس
if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USERNAME" ] || [ -z "$DB_PASSWORD" ]; then
    echo "Error: One or more database environment variables (DB_HOST, DB_NAME, DB_USERNAME, DB_PASSWORD) are not set."
    exit 1
fi
echo "Database variables are set: DB_HOST=$DB_HOST, DB_NAME=$DB_NAME"
# اجرای سرور PHP
exec php -S 0.0.0.0:$PORT -t /var/www/html