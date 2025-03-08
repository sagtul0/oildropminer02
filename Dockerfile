FROM php:7.4-apache

# نصب پیش‌نیازهای PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# کپی کردن فایل‌های پروژه
COPY . /var/www/html
RUN chmod -R 755 /var/www/html

# تنظیم پورت
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:${PORT:-8080}", "telegram.php"]