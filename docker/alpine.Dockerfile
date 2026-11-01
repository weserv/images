# Based on:
# https://github.com/nginx/pkg-oss/blob/master/alpine/Makefile
# https://github.com/nginxinc/docker-nginx/blob/master/mainline/alpine/Dockerfile
FROM alpine:3.24

LABEL maintainer="Kleis Auke Wolthuizen <info@kleisauke.nl>"

ARG NGINX_VERSION=1.31.8

# Copy the contents of this repository to the container
COPY . /var/www/imagesweserv
WORKDIR /var/www/imagesweserv

# Create nginx user/group
RUN addgroup -g 101 -S nginx \
    && adduser -S -D -H -u 101 -h /var/cache/nginx -s /sbin/nologin -G nginx -g nginx nginx \
    # Bring in build dependencies
    && apk add --no-cache --virtual .build-deps \
        build-base \
        curl \
        git \
        meson \
        openssl-dev \
        pcre2-dev \
        vips-dev \
    # Build and install Meson-based project
    && meson setup build --prefix=/usr -Dcli=true \
    && meson compile -C build \
    && meson install -C build \
    # Build and install nginx along with the weserv module
    && mkdir nginx \
    && curl -Ls https://nginx.org/download/nginx-$NGINX_VERSION.tar.gz | \
        tar xzC nginx --strip-components=1 \
    && cd nginx \
    && ./configure \
        --prefix=/etc/nginx \
        --sbin-path=/usr/sbin/nginx \
        --modules-path=/usr/lib/nginx/modules \
        --conf-path=/etc/nginx/nginx.conf \
        --error-log-path=/var/log/nginx/error.log \
        --http-log-path=/var/log/nginx/access.log \
        --http-client-body-temp-path=/var/cache/nginx/client_temp \
        --http-proxy-temp-path=/var/cache/nginx/proxy_temp \
        --http-fastcgi-temp-path=/var/cache/nginx/fastcgi_temp \
        --http-uwsgi-temp-path=/var/cache/nginx/uwsgi_temp \
        --http-scgi-temp-path=/var/cache/nginx/scgi_temp \
        --pid-path=/run/nginx.pid \
        --lock-path=/run/nginx.lock \
        --user=nginx \
        --group=nginx \
        --add-module=/var/www/imagesweserv \
        --with-file-aio \
        --with-http_ssl_module \
        --with-http_v2_module \
        --with-http_realip_module \
        --with-http_stub_status_module \
        --with-http_secure_link_module \
        --with-pcre-jit \
    && make -j$(nproc) \
    && make install \
    && cd ../ \
    # Remove build directories and dependencies
    && rm -rf build nginx \
    && apk del --no-network .build-deps \
    # Bring in runtime dependencies
    && apk add --no-cache \
        imagemagick \
        libheif-svtenc \
        openssl \
        pcre2 \
        vips-cpp \
        vips-jxl \
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

COPY <<EOF /etc/ImageMagick-7/policy.xml
<policymap>
  <!--
  Use IM only to read certain image types. Assumes the rest is handled in libvips.
  Note: ICO data can be either a BMP image, or a complete PNG image.
  -->
  <policy domain="delegate" rights="none" pattern="*" />
  <policy domain="filter" rights="none" pattern="*" />
  <policy domain="coder" rights="none" pattern="*" />
  <policy domain="coder" rights="read" pattern="{ICO,BMP,PNG}" />
</policymap>
EOF

# Set default timezone (can be overridden with -e "TZ=Continent/City")
ENV TZ=Europe/Amsterdam \
    # Increase the minimum stack size to 2MB
    VIPS_MIN_STACK_SIZE=2m

EXPOSE 80

STOPSIGNAL SIGQUIT

CMD ["nginx", "-g", "daemon off;"]
