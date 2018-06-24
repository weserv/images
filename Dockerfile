FROM centos:7

MAINTAINER Kleis Auke Wolthuizen <info@kleisauke.nl>

# Set default timezone.
# An alternative way to set timezone is to run container with: -e "TZ=Continent/City".
ENV TZ Europe/Amsterdam
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure NGINX repo
RUN echo -e '\
[nginx]\n\
name=nginx repo\n\
baseurl=http://nginx.org/packages/mainline/centos/7/$basearch/\n\
gpgcheck=0\n\
enabled=1' > /etc/yum.repos.d/nginx.repo

# Install PHP, PHP extensions, composer, redis, supervisor and nginx
RUN yum update -y && \
    yum install -y epel-release yum-utils && \
    yum install -y https://rpms.remirepo.net/enterprise/remi-release-7.rpm && \
    yum-config-manager --enable remi-php72 remi && \
    yum install -y \
        composer \
        nginx \
        php \
        php-fpm \
        php-intl \
        php-opcache \
        php-pecl-redis \
        php-pecl-vips \
        redis \
        supervisor && \
    yum clean all

# Add nginx configurations
ADD config/nginx.conf /etc/nginx/nginx.conf
ADD config/imagesweserv.nginxconf /etc/nginx/sites-available/default.conf
RUN mkdir /etc/nginx/sites-enabled && \
    ln -s /etc/nginx/sites-available/default.conf /etc/nginx/sites-enabled/default.conf

# Add PHP configurations
ADD config/php.ini /etc/php.ini
ADD config/php-fpm.conf /etc/php-fpm.conf

# Add Supervisor configuration
ADD config/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/imagesweserv

# Define mountable directories
VOLUME ["/var/www/imagesweserv", "/var/log/supervisor", "/dev/shm"]

EXPOSE 80

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]