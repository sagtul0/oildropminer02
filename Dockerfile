FROM php:8.1-apache

# نصب پیش‌نیازهای PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pgsql

# کپی کردن فایل‌ها و اسکریپت
COPY . /var/www/html
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

# تنظیم پورت
EXPOSE 8080
CMD ["entrypoint.sh"]