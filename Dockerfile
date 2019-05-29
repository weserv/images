FROM centos:7

MAINTAINER Kleis Auke Wolthuizen <info@kleisauke.nl>

# Set default timezone.
# An alternative way to set timezone is to run container with: -e "TZ=Continent/City".
ENV TZ Europe/Amsterdam
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Import archive signing keys and update packages
RUN rpm --import https://dl.fedoraproject.org/pub/epel/RPM-GPG-KEY-EPEL-7 && \
    rpm --import https://rpms.remirepo.net/RPM-GPG-KEY-remi && \
    rpm --import https://sourceforge.net/projects/libjpeg-turbo/files/LJT-GPG-KEY && \
    rpm --import https://openresty.org/package/pubkey.gpg && \
    rpmkeys --import file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CentOS-7 && \
    yum update -y

# Install the latest version of libjpeg-turbo,
# since the version on CentOS is too old (v1.2.90)
RUN yum install -y yum-utils && \
    yum-config-manager --add-repo https://libjpeg-turbo.org/pmwiki/uploads/Downloads/libjpeg-turbo.repo && \
    yum install -y libjpeg-turbo-official && \
    echo '/opt/libjpeg-turbo/lib64' >> /etc/ld.so.conf.d/libjpeg-turbo-official-x86_64.conf && \
    ldconfig

# Update the PKG_CONFIG_PATH environment variable,
# since libjpeg-turbo is installed in a non-standard prefix
ENV PKG_CONFIG_PATH=/opt/libjpeg-turbo/lib64/pkgconfig:$PKG_CONFIG_PATH

# Install openresty (+ command-line utility), libvips, redis, and supervisor
RUN yum install -y epel-release && \
    yum localinstall -y --nogpgcheck https://rpms.remirepo.net/enterprise/remi-release-7.rpm && \
    yum-config-manager --enable remi && \
    yum localinstall -y --nogpgcheck https://download1.rpmfusion.org/free/el/rpmfusion-free-release-7.noarch.rpm && \
    rpmkeys --import file:///etc/pki/rpm-gpg/RPM-GPG-KEY-rpmfusion-free-el-7 && \
    yum-config-manager --add-repo https://openresty.org/package/centos/openresty.repo && \
    yum groupinstall -y "Development Tools" && \
    yum install -y --setopt=tsflags=nodocs \
        openresty \
        openresty-resty \
        vips-full \
        redis \
        supervisor && \
    yum clean all

# Install LuaRocks
RUN curl -L https://luarocks.github.io/luarocks/releases/luarocks-3.1.2.tar.gz | tar xz && \
    cd luarocks-3.1.2/ && \
    ./configure \
        --prefix=/usr/local/openresty/luajit \
        --with-lua=/usr/local/openresty/luajit/ \
        --lua-suffix=jit-2.1.0-beta3 \
        --with-lua-include=/usr/local/openresty/luajit/include/luajit-2.1 && \
    make build -j$(nproc) && \
    make install

# Enable networking, see: https://github.com/openresty/openresty-packaging/issues/28
RUN echo "NETWORKING=yes" >> /etc/sysconfig/network

# Forward nginx request and error logs to docker log collector
RUN mkdir -p /var/log/nginx && \
    ln -sf /dev/stdout /usr/local/openresty/nginx/logs/nginx-access.log && \
    ln -sf /dev/stderr /usr/local/openresty/nginx/logs/nginx-error.log

# Add additional binaries into PATH for convenience
ENV PATH=/usr/local/openresty/luajit/bin:/usr/local/openresty/nginx/sbin:/usr/local/openresty/bin:$PATH

# lua-vips is searching for libvips.so instead of libvips.so.42
RUN ln -sf /usr/lib64/libvips.so.42 /usr/lib64/libvips.so

# Copy the contents of this repository to the container
COPY . /var/www/imagesweserv

# Alternatively, clone the repository
# RUN git clone https://github.com/weserv/images.git /var/www/imagesweserv

WORKDIR /var/www/imagesweserv

# Copy nginx configuration
COPY config/nginx/nginx.conf /usr/local/openresty/nginx/conf/nginx.conf
COPY config/nginx/conf.d /usr/local/openresty/nginx/conf/conf.d/

# Copy Supervisor configuration
COPY config/supervisord.conf /etc/supervisord.conf

# Install LuaRocks dependencies
RUN make dev

EXPOSE 80

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]