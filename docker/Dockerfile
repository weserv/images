# See https://github.com/docker-library/php/blob/3632e1e8ce2d6aa7021560998b7acf43ad1a26d1/7.1/Dockerfile
FROM php:7.1-fpm

MAINTAINER Kleis Auke Wolthuizen <info@kleisauke.nl>

# Install dependencies
RUN apt-get update && \
  DEBIAN_FRONTEND=noninteractive apt-get install -y \
  automake build-essential curl fftw3-dev git \
  gobject-introspection graphicsmagick \
  graphicsmagick-libmagick-dev-compat gtk-doc-tools \
  libcfitsio3-dev libexif-dev libfreetype6-dev libgif-dev \
  libglib2.0-dev libgsf-1-dev libicu-dev libjpeg62-turbo-dev \
  libmagickwand-dev libmatio-dev libopenslide-dev \
  liborc-0.4-dev libpango1.0-dev libpng12-dev \
  libpoppler-glib-dev librsvg2-dev libtiff5-dev libwebp-dev \
  libxml2-dev swig unzip

ENV LIBVIPS_VERSION_MAJOR 8
ENV LIBVIPS_VERSION_MINOR 5
ENV LIBVIPS_VERSION_PATCH 6
ENV LIBVIPS_VERSION $LIBVIPS_VERSION_MAJOR.$LIBVIPS_VERSION_MINOR.$LIBVIPS_VERSION_PATCH

RUN \
  # Build libvips
  cd /tmp && \
  curl -L -O https://github.com/jcupitt/libvips/releases/download/v$LIBVIPS_VERSION/vips-$LIBVIPS_VERSION.tar.gz && \
  tar zxvf vips-$LIBVIPS_VERSION.tar.gz && \
  cd /tmp/vips-$LIBVIPS_VERSION && \
  ./configure --enable-debug=no --without-python --with-magickpackage=GraphicsMagick $1 && \
  make && \
  make install && \
  ldconfig

# Configure GD (for GIF support)
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/

# Install project PHP extensions
RUN docker-php-ext-install \
    gd \
    intl \
    opcache

# Set recommended PHP.ini settings
# See https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
		echo 'opcache.enable_cli=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer --version

# Set timezone
RUN rm /etc/localtime \
    && ln -s /usr/share/zoneinfo/Europe/Amsterdam /etc/localtime \
    && "date"

# Install php-vips-ext
RUN pecl install vips \
    && docker-php-ext-enable vips

# Install PHP Redis Extension
RUN pecl install redis \
    && docker-php-ext-enable redis

RUN \
  # Clean up
  apt-get remove -y automake build-essential && \
  apt-get autoremove -y && \
  apt-get autoclean && \
  apt-get clean && \
  rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /var/www/imagesweserv