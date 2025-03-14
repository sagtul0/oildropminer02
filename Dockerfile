FROM php:8.1-apache

# نصب وابستگی‌ها و افزونه‌های PDO و PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pgsql \
    && docker-php-ext-enable pdo_pgsql

# کپی فایل تنظیمات PHP
COPY php.ini /usr/local/etc/php/

# کپی فایل‌ها
COPY . /var/www/html
COPY entrypoint.sh /usr/local/bin/

# دادن دسترسی اجرایی
RUN chmod +x /usr/local/bin/entrypoint.sh

# تنظیمات Apache
RUN a2enmod rewrite

# اجرای اسکریپت ورود
CMD ["entrypoint.sh"]