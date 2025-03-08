FROM php:7.4-apache
RUN docker-php-ext-install pdo pdo_pgsql
COPY . /var/www/html
RUN chmod -R 755 /var/www/html
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:${PORT:-8080}", "telegram.php"]