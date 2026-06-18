FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev \
 && docker-php-ext-install pdo pdo_pgsql \
 && a2enmod rewrite \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY slim/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]