FROM php:8.2-apache

# نصب وابستگی‌ها و افزونه‌های PHP
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pgsql pdo_pgsql

# تنظیمات افزونه‌ها
RUN echo "extension=pgsql.so" > /usr/local/etc/php/conf.d/pgsql.ini
RUN echo "extension=pdo_pgsql.so" > /usr/local/etc/php/conf.d/pdo_pgsql.ini

# دیباگ برای چک کردن ماژول‌ها و وجود فایل‌ها
RUN echo "Checking PHP modules..." > /var/www/html/php_modules.log
RUN php -m >> /var/www/html/php_modules.log
RUN echo "Listing extension directory contents..." >> /var/www/html/php_modules.log
RUN ls -la /usr/local/lib/php/extensions/no-debug-non-zts-20220829/ >> /var/www/html/php_modules.log

# کپی فایل‌ها
COPY . /var/www/html
COPY entrypoint.sh /usr/local/bin/

# دادن دسترسی اجرایی
RUN chmod +x /usr/local/bin/entrypoint.sh

# تنظیمات Apache
RUN a2enmod rewrite

# اجرای اسکریپت ورود
CMD ["entrypoint.sh"]