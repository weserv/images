FROM rockylinux:8

LABEL maintainer="Kleis Auke Wolthuizen <info@kleisauke.nl>"

ARG NGINX_VERSION=1.23.0

# Copy the contents of this repository to the container
COPY . /var/www/imagesweserv
WORKDIR /var/www/imagesweserv

# Set default timezone (can be overridden with -e "TZ=Continent/City")
ENV TZ=Europe/Amsterdam \
    # Update the PKG_CONFIG_PATH environment variable,
    # since libjpeg-turbo is installed in a non-standard prefix
    PKG_CONFIG_PATH=/opt/libjpeg-turbo/lib64/pkgconfig

# Update packages
RUN dnf update -y \
    # Install the latest stable version of libjpeg-turbo,
    # since the version on RHEL 8 is too old (v1.5.3)
    && dnf install -y dnf-plugins-core \
    && dnf config-manager --add-repo https://libjpeg-turbo.org/pmwiki/uploads/Downloads/libjpeg-turbo.repo \
    && sed -i '/^exclude=/ s/$/,libjpeg-turbo-*.*.9[0-9]-*/' /etc/yum.repos.d/libjpeg-turbo.repo \
    && dnf install -y libjpeg-turbo-official \
    && echo '/opt/libjpeg-turbo/lib64' >> /etc/ld.so.conf.d/libjpeg-turbo-official-x86_64.conf \
    && ldconfig \
    # Install libvips and needed dependencies
    && dnf install -y epel-release \
    && dnf config-manager --set-enabled powertools \
    && dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm \
    && dnf config-manager --set-enabled remi \
    && dnf install -y --nogpgcheck https://mirrors.rpmfusion.org/free/el/rpmfusion-free-release-8.noarch.rpm \
    && dnf group install -y --with-optional 'Development Tools' \
    && dnf install -y --setopt=tsflags=nodocs --setopt=install_weak_deps=False \
        vips-devel \
        vips-heif \
        vips-poppler \
        vips-magick-im6 \
        openssl-devel \
        pcre2-devel \
        zlib-devel \
        nginx-filesystem \
    # Build CMake-based project
    && cmake -S . -B _build \
        -DCMAKE_BUILD_TYPE=Release \
        -DBUILD_TOOLS=ON \
        -DNGX_VERSION=$NGINX_VERSION \
        -DCUSTOM_NGX_FLAGS="--prefix=/usr/share/nginx;\
--sbin-path=/usr/sbin/nginx;\
--modules-path=/usr/lib64/nginx/modules;\
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
    && cmake --build _build -- -j$(nproc) \
    && ldconfig \
    # Remove build directory and dependencies
    && rm -rf _build \
    && dnf group remove -y 'Development Tools' \
    && dnf remove -y \
        vips-devel \
        openssl-devel \
        pcre2-devel \
        zlib-devel \
    && dnf clean all \
    # Ensure nginx directories exist with the correct permissions
    && mkdir -m 700 /var/lib/nginx \
    && mkdir -m 700 /var/lib/nginx/tmp \
    && mkdir -m 700 /usr/lib64/nginx \
    && mkdir -m 755 /usr/lib64/nginx/modules \
    # Forward request and error logs to docker log collector
    && ln -sf /dev/stdout /var/log/nginx/weserv-access.log \
    && ln -sf /dev/stderr /var/log/nginx/weserv-error.log \
    # Copy nginx configuration to the appropriate location
    && cp ngx_conf/*.conf /etc/nginx

EXPOSE 80

STOPSIGNAL SIGQUIT

CMD ["nginx", "-g", "daemon off;"]
