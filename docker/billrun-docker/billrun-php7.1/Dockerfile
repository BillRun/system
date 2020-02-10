from php:7.1-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install -y \
        wkhtmltopdf \
#        openssl \
    && pecl install yaf \
    && pecl install mongodb \
    && pecl install xdebug \
    && docker-php-ext-enable yaf mongodb xdebug

COPY php-fpm.conf /usr/local/etc/
COPY php.ini /usr/local/etc/php/
CMD ["php-fpm"]
