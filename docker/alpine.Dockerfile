FROM alpine:3.13.6

RUN apk add --update-cache \
  build-base cmake git \
  libjpeg-turbo \
  openssl openssl-dev \
  vips vips-dev

# Create nginx user and group
RUN addgroup -S nginx && adduser -S nginx -G nginx

# Copy the contents of this repository to the container
COPY . /var/www/imagesweserv

WORKDIR /var/www/imagesweserv/build

# Build CMake-based project
ARG NGINX_VERSION=1.21.3
RUN cmake .. \
  -DCMAKE_BUILD_TYPE=Release \
  -DBUILD_TOOLS=ON \
  -DNGX_VERSION=$NGINX_VERSION \
  -DCUSTOM_NGX_FLAGS="--prefix=/usr/share/nginx;\
--sbin-path=/usr/sbin/nginx;\
--modules-path=/usr/lib/nginx/modules;\
--conf-path=/etc/nginx/nginx.conf;\
--error-log-path=/var/log/nginx/error.log;\
--http-log-path=/var/log/nginx/access.log;\
--http-client-body-temp-path=/var/lib/nginx/tmp/client_body;\
--http-proxy-temp-path=/var/lib/nginx/tmp/proxy;\
--http-fastcgi-temp-path=/var/lib/nginx/tmp/fastcgi;\
--http-uwsgi-temp-path=/var/lib/nginx/tmp/uwsgi;\
--http-scgi-temp-path=/var/lib/nginx/tmp/scgi;\
--pid-path=/run/nginx.pid;\
--lock-path=/run/lock/subsys/nginx;\
--user=nginx;\
--group=nginx" \
  && make -j"$(nproc)" \
  && cd .. \
  && rm -Rf build /var/cache/* \
  # Cleanup build dependencies
  && apk del build-base cmake git openssl-dev vips-dev

WORKDIR /var/www/imagesweserv

# Ensure nginx directories exist
RUN mkdir -m 700 /var/lib/nginx \
  && mkdir -m 700 /var/lib/nginx/tmp \
  && mkdir -m 700 /usr/lib/nginx \
  && mkdir -m 755 /usr/lib/nginx/modules \
  # Forward request and error logs to docker log collector
  && ln -sf /dev/stdout /var/log/nginx/weserv-access.log \
  && ln -sf /dev/stderr /var/log/nginx/weserv-error.log \
  # Copy nginx configuration to the appropriate location
  && cp /var/www/imagesweserv/ngx_conf/*.conf /etc/nginx

EXPOSE 80

ENTRYPOINT ["nginx", "-g", "daemon off;"]