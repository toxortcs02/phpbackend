FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev unzip \
 && docker-php-ext-install pdo pdo_pgsql \
 && a2enmod rewrite \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html/

RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]