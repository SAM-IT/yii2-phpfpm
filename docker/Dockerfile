FROM php:7.4-fpm-alpine
MAINTAINER Sam Mousa <sam@mousa.nl>

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions
RUN install-php-extensions \
  gd \
  intl \
  pdo_mysql \
  sockets \
  pcov
RUN apk add --update --no-cache jq
ADD entrypoint.sh /
ENTRYPOINT ["/entrypoint.sh"]
VOLUME /runtime
WORKDIR /project
ADD php-fpm.conf /