FROM php:8.3-fpm
WORKDIR /var/www
RUN apt-get update && apt-get install -y libpng-dev zip unzip curl \
    && docker-php-ext-install pdo pdo_mysql gd
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --optimize-autoloader
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]