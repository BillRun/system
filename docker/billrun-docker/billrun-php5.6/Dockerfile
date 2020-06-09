from php:5.6-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install -y \
        wkhtmltopdf \
#       openssl \
        libssl-dev \
    && pecl download yaf-2.3.5 && tar zxvf yaf-2.3.5.tgz && cd yaf-2.3.5 && phpize && ./configure && make && make install \
    && pecl install mongo \
    && pecl install xdebug-2.5.5 \
    && docker-php-ext-enable yaf mongo xdebug

COPY php-fpm.conf /usr/local/etc/
COPY php.ini /usr/local/etc/php/
CMD ["php-fpm"]
