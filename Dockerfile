FROM centos:7

ARG NGINX_VERSION=1.17.4

LABEL maintainer "Kleis Auke Wolthuizen <info@kleisauke.nl>"

# Set default timezone
# An alternative way to set timezone is to run container with: -e "TZ=Continent/City"
ENV TZ Europe/Amsterdam
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone

# Import archive signing keys and update packages
RUN rpm --import https://dl.fedoraproject.org/pub/epel/RPM-GPG-KEY-EPEL-7 \
    && rpm --import https://rpms.remirepo.net/RPM-GPG-KEY-remi \
    && rpm --import https://sourceforge.net/projects/libjpeg-turbo/files/LJT-GPG-KEY \
    && rpmkeys --import file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-7 \
    && yum update -y

# Install the latest version of libjpeg-turbo,
# since the version on CentOS is too old (v1.2.90)
RUN yum install -y yum-utils \
    && yum-config-manager --add-repo https://libjpeg-turbo.org/pmwiki/uploads/Downloads/libjpeg-turbo.repo \
    && yum install -y libjpeg-turbo-official \
    && echo '/opt/libjpeg-turbo/lib64' >> /etc/ld.so.conf.d/libjpeg-turbo-official-x86_64.conf \
    && ldconfig

# Update the PKG_CONFIG_PATH environment variable,
# since libjpeg-turbo is installed in a non-standard prefix
ENV PKG_CONFIG_PATH=/opt/libjpeg-turbo/lib64/pkgconfig${PKG_CONFIG_PATH:+:${PKG_CONFIG_PATH}}

# Install libvips and needed dependencies
RUN yum install -y epel-release centos-release-scl \
    && rpmkeys --import file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-SIG-SCLo \
    && yum localinstall -y --nogpgcheck https://rpms.remirepo.net/enterprise/remi-release-7.rpm \
    && yum-config-manager --enable remi \
    && yum localinstall -y --nogpgcheck https://download1.rpmfusion.org/free/el/rpmfusion-free-release-7.noarch.rpm \
    && rpmkeys --import file:///etc/pki/rpm-gpg/RPM-GPG-KEY-rpmfusion-free-el-7  \
    && yum install -y --setopt=tsflags=nodocs \
        git \
        cmake3 \
        vips-full-devel \
        devtoolset-8-make \
        devtoolset-8-gcc \
        devtoolset-8-gcc-c++ \
        openssl-devel \
        pcre-devel \
        zlib-devel

# Create nginx user and group
RUN groupadd nginx \
    && useradd -r -g nginx -s /sbin/nologin -c "Nginx web server" nginx

# Clone the repository
RUN git clone --recursive https://github.com/weserv/images.git /var/www/imagesweserv

WORKDIR /var/www/imagesweserv

# Use the C and C++ compiler from devtoolset-8
ENV CC=/opt/rh/devtoolset-8/root/usr/bin/gcc \
    CXX=/opt/rh/devtoolset-8/root/usr/bin/g++ \
    PATH=/opt/rh/devtoolset-8/root/usr/bin${PATH:+:${PATH}}

# Build CMake-based project
RUN mkdir build \
    && cd build \
    && cmake3 .. \
       -DCMAKE_BUILD_TYPE=Release \
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
    && make -j$(nproc) \
    && ldconfig \
    && cd ..

# Ensure nginx directories exist
RUN mkdir -p -m 700 /var/lib/nginx \
    && mkdir -p -m 700 /var/lib/nginx/tmp \
    && mkdir -p -m 700 /var/log/nginx \
    && mkdir -p -m 755 /usr/share/nginx/html \
    && mkdir -p -m 755 /usr/lib64/nginx/modules 

# Forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/weserv-access.log \
    && ln -sf /dev/stderr /var/log/nginx/weserv-error.log

# Copy nginx config to the appropriate location
RUN cp -r /var/www/imagesweserv/ngx_conf/. /etc/nginx

EXPOSE 80

STOPSIGNAL SIGTERM

ENTRYPOINT ["nginx", "-g", "daemon off;"]
