# Based on:
# https://hg.nginx.org/pkg-oss/file/tip/alpine/Makefile
# https://github.com/nginxinc/docker-nginx/blob/master/mainline/alpine/Dockerfile
FROM alpine:3.19

LABEL maintainer="Kleis Auke Wolthuizen <info@kleisauke.nl>"

ARG NGINX_VERSION=1.25.4

# Copy the contents of this repository to the container
COPY . /var/www/imagesweserv
WORKDIR /var/www/imagesweserv

# Create nginx user/group first, to be consistent throughout docker variants
RUN addgroup -g 101 -S nginx \
    && adduser -S -D -H -u 101 -h /var/cache/nginx -s /sbin/nologin -G nginx -g nginx nginx \
    # Bring in build dependencies
    && apk add --no-cache --virtual .build-deps \
        build-base \
        cmake \
        git \
        openssl-dev \
        pcre2-dev \
        vips-dev \
    # Build CMake-based project
    && cmake -S . -B _build \
        -DCMAKE_BUILD_TYPE=Release \
        -DBUILD_TOOLS=ON \
        -DNGX_VERSION=$NGINX_VERSION \
        -DCUSTOM_NGX_FLAGS="--prefix=/etc/nginx;\
--sbin-path=/usr/sbin/nginx;\
--modules-path=/usr/lib/nginx/modules;\
--conf-path=/etc/nginx/nginx.conf;\
--error-log-path=/var/log/nginx/error.log;\
--http-log-path=/var/log/nginx/access.log;\
--http-client-body-temp-path=/var/cache/nginx/client_temp;\
--http-proxy-temp-path=/var/cache/nginx/proxy_temp;\
--http-fastcgi-temp-path=/var/cache/nginx/fastcgi_temp;\
--http-uwsgi-temp-path=/var/cache/nginx/uwsgi_temp;\
--http-scgi-temp-path=/var/cache/nginx/scgi_temp;\
--pid-path=/var/run/nginx.pid;\
--lock-path=/var/run/nginx.lock;\
--user=nginx;\
--group=nginx" \
    && cmake --build _build -- -j$(nproc) \
    # Remove build directory and dependencies
    && rm -rf _build \
    && apk del .build-deps \
    # Bring in runtime dependencies
    && apk add --no-cache \
        openssl \
        pcre2 \
        vips-cpp \
        vips-heif \
        vips-magick \
        vips-poppler \
    # Bring in tzdata so users could set the timezones through the environment variables
    && apk add --no-cache tzdata \
    # Ensure nginx cache directory exist with the correct permissions
    && mkdir -m 700 /var/cache/nginx \
    # Forward request and error logs to docker log collector
    && ln -sf /dev/stdout /var/log/nginx/weserv-access.log \
    && ln -sf /dev/stderr /var/log/nginx/weserv-error.log \
    # Copy nginx configuration to the appropriate location
    && cp ngx_conf/*.conf /etc/nginx

# Set default timezone (can be overridden with -e "TZ=Continent/City")
ENV TZ=Europe/Amsterdam \
    # Increase the minimum stack size to 2MB
    VIPS_MIN_STACK_SIZE=2m

EXPOSE 80

STOPSIGNAL SIGQUIT

CMD ["nginx", "-g", "daemon off;"]
