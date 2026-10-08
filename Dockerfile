FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite

COPY docker-entrypoint.sh /usr/local/bin/redmanfinance-entrypoint
RUN chmod +x /usr/local/bin/redmanfinance-entrypoint

COPY . /var/www/html/

EXPOSE 10000
ENTRYPOINT ["redmanfinance-entrypoint"]