FROM php:7.4-fpm
# https://github.com/netroby/docker-php-fpm/blob/master/Dockerfile

RUN apt-get update && apt-get install -y \
    wkhtmltopdf libpng-dev zlib1g-dev libzip-dev libgmp-dev libcurl4-openssl-dev pkg-config libssl-dev wget \
        unzip openjdk-11-jre-headless \
    && pecl install yaf-3.1.4 \
    && pecl install mongodb-1.11.1 \
    && docker-php-ext-enable yaf mongodb \
    && docker-php-ext-install pcntl bcmath gd zip sockets gmp \    
    && apt-get clean

RUN  wget 'https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.5/wkhtmltox_0.12.5-1.buster_amd64.deb' \
	&& apt install -y ./wkhtmltox_0.12.5-1.buster_amd64.deb

# install jsignpdf
RUN wget -O /tmp/jsignpdf.zip https://github.com/JSignPdf/JSignPdfBin/archive/refs/tags/1.0.1.zip
RUN unzip /tmp/jsignpdf.zip -d /tmp
RUN mv /tmp/JSignPdfBin-1.0.1/bin/jsignpdf-1.6.4/ /opt
RUN rm -rf /tmp/*

WORKDIR /billrun
COPY docker/billrun-docker/billrun-php74/php-fpm.conf /usr/local/etc/
COPY docker/billrun-docker/billrun-php74/php.ini /usr/local/etc/php/
COPY ./ /billrun
RUN mkdir -p /billrun/logs && touch /billrun/logs/debug.log && chmod 777 /billrun/logs/debug.log
RUN mkdir -p /billrun/logs/container && touch /billrun/logs/container/debug.log && chmod 777 /billrun/logs/container/debug.log
RUN sed 's#\(log.debug.writerParams.stream=\).*#\1"php://stderr"#' -i /billrun/conf/container.ini
RUN sed 's#/logs/debug.log#php://stderr#' -i mongo/base/config.export
CMD ["php-fpm"]
