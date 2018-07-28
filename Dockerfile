FROM centos:7

MAINTAINER Kleis Auke Wolthuizen <info@kleisauke.nl>

# Set default timezone.
# An alternative way to set timezone is to run container with: -e "TZ=Continent/City".
ENV TZ Europe/Amsterdam
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install openresty (+ command-line utility), libvips, redis, and supervisor
RUN yum update -y && \
    yum install -y epel-release yum-utils && \
    yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm && \
    yum-config-manager --enable remi && \
    yum-config-manager --add-repo https://openresty.org/package/centos/openresty.repo && \
    yum groupinstall -y "Development Tools" && \
    yum install -y \
        openresty \
        openresty-resty \
        vips \
        redis \
        supervisor && \
    yum clean all

# Install LuaRocks
RUN curl -L https://luarocks.github.io/luarocks/releases/luarocks-2.4.4.tar.gz | tar xz && \
    cd luarocks-2.4.4/ && \
    ./configure \
        --prefix=/usr/local/openresty/luajit \
        --with-lua=/usr/local/openresty/luajit/ \
        --lua-suffix=jit-2.1.0-beta3 \
        --with-lua-include=/usr/local/openresty/luajit/include/luajit-2.1 && \
    make build -j$(nproc) && \
    make install

# Add nginx configuration
ADD config/nginx/nginx.conf /usr/local/openresty/nginx/conf/nginx.conf

# Add Supervisor configuration
ADD config/supervisord.conf /etc/supervisord.conf

# Enable networking
RUN echo "NETWORKING=yes" >> /etc/sysconfig/network

# Forward nginx request and error logs to docker log collector
RUN mkdir -p /var/log/nginx && \
    ln -sf /dev/stdout /var/log/nginx/access.log && \
	ln -sf /dev/stderr /var/log/nginx/error.log

# Add additional binaries into PATH for convenience
ENV PATH=/usr/local/openresty/luajit/bin:/usr/local/openresty/nginx/sbin:/usr/local/openresty/bin:$PATH

# lua-vips is searching for libvips.so instead of libvips.so.42
RUN ln -sf /usr/lib64/libvips.so.42 /usr/lib64/libvips.so

WORKDIR /var/www/imagesweserv

# Define mountable directories
VOLUME ["/var/www/imagesweserv", "/etc/nginx/conf.d", "/dev/shm"]

EXPOSE 80

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]