FROM php:7.2-apache

MAINTAINER Robert Turner

COPY . /var/www/html
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www

# Entrypoint script to support heroku $PORT env var at runtime
COPY .docker/run-apache2.sh /usr/local/bin
CMD [ "run-apache2.sh" ]
