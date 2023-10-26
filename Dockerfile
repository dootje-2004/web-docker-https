FROM php:8-apache

COPY server.conf /etc/apache2/sites-available/server.conf

RUN a2ensite server.conf && a2dissite 000-default.conf
