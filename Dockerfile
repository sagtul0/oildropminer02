# استفاده از تصویر پایه PHP 8.2 با Apache
FROM php:8.2-apache

# نصب وابستگی‌های سیستمی و کتابخانه‌های مورد نیاز PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# پیکربندی و نصب افزونه‌های PHP (pdo, pgsql, pdo_pgsql)
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pgsql pdo_pgsql

# تنظیم فایل‌های ini برای فعال‌سازی ماژول‌ها
RUN echo "extension=pgsql.so" > /usr/local/etc/php/conf.d/pgsql.ini
RUN echo "extension=pdo_pgsql.so" > /usr/local/etc/php/conf.d/pdo_pgsql.ini

# دیباگ برای بررسی ماژول‌ها و محتوای دایرکتوری افزونه‌ها
RUN echo "Checking PHP modules..." > /var/www/html/php_modules.log
RUN php -m >> /var/www/html/php_modules.log
RUN echo "Listing extension directory contents..." >> /var/www/html/php_modules.log
RUN ls -la /usr/local/lib/php/extensions/no-debug-non-zts-20220829/ >> /var/www/html/php_modules.log
RUN echo "Loaded PHP configuration file: $(php -i | grep 'Loaded Configuration File' | cut -d' ' -f4)" >> /var/www/html/php_modules.log

# کپی فایل‌های پروژه و اسکریپت ورود
COPY . /var/www/html
COPY entrypoint.sh /usr/local/bin/

# دادن دسترسی اجرایی به اسکریپت ورود
RUN chmod +x /usr/local/bin/entrypoint.sh

# فعال‌سازی ماژول rewrite برای Apache
RUN a2enmod rewrite

# اجرای اسکریپت ورود به‌عنوان دستور اصلی
CMD ["entrypoint.sh"]