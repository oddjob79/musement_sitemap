FROM php:7.2-apache

MAINTAINER Robert Turner

COPY . /var/www/html
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

CMD echo "ServerName localhost" >> /etc/apache2/apache2.conf
CMD ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]

RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www
