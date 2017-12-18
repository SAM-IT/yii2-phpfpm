FROM alpine:edge
COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT /entrypoint.sh
ARG phpconf=/etc/php7/php-fpm.conf
RUN apk add \
    php7 \
    php7-fpm \
    ca-certificates \
    --update --no-cache
COPY php-fpm.conf $phpconf

RUN mkdir -p /tmp/runtime \
    && mkdir -p /tmp/assets \
    && chown nobody:nobody /tmp/runtime \
    && chown nobody:nobody /tmp/assets


RUN php-fpm7 -t
