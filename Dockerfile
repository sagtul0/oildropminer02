FROM php:8.1-apache

# نصب وابستگی‌ها و افزونه‌های PDO و PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pgsql

# تنظیم دستی افزونه‌ها توی فایل‌های جداگانه
RUN echo "extension=pdo.so" > /usr/local/etc/php/conf.d/pdo.ini
RUN echo "extension=pgsql.so" > /usr/local/etc/php/conf.d/pgsql.ini
RUN echo "extension=pdo_pgsql.so" > /usr/local/etc/php/conf.d/pdo_pgsql.ini

# کپی فایل تنظیمات PHP (در صورت نیاز)
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