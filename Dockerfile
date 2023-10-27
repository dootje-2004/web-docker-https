FROM php:8-apache

COPY server.conf /etc/apache2/sites-available/server.conf
COPY server.crt /etc/apache2/ssl/server.crt
COPY server.key /etc/apache2/ssl/server.key
COPY pk-passphrase-for-apache.sh /etc/apache2/ssl/passphrase.sh
COPY ssl-passphrase.conf /etc/apache2/conf-available/ssl-passphrase.conf

RUN a2enmod ssl && a2enconf ssl-passphrase && a2ensite server.conf && a2dissite 000-default.conf
