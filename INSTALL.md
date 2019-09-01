# Install instructions (for CentOS 7)

## Build dependencies

 * `cmake` >= 3.11
 * `g++` => 5.0
 * `libpcre3` (for nginx rewrite module)
 * `openssl` (for nginx ssl support)
 * `libvips` >= 8.8

## Install instructions

```bash
# Install the EPEL repository configuration package
yum install https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm

# Install the Remi repository configuration package
yum install http://rpms.remirepo.net/enterprise/remi-release-7.rpm

# Install the Software Collections (SCL) package
yum install centos-release-scl

# Install the yum-utils package (for the yum-config-manager command)
yum install yum-utils

# Command to enable the repository
yum-config-manager --enable remi

# Install libvips 8.8 (full-fat version)
yum install vips-full-devel

# Install build requirements
yum install \
  autoconf \
  automake \
  cmake3 \
  devtoolset-8-make \
  devtoolset-8-gcc \
  devtoolset-8-gcc-c++ \
  git \
  glibc-devel \
  glibc-headers \
  openssl-devel \
  pcre-devel \
  zlib-devel
```

## Build

```bash
# Use the C and C++ compiler from devtoolset-8
export CC=/opt/rh/devtoolset-8/root/usr/bin/gcc
export CXX=/opt/rh/devtoolset-8/root/usr/bin/g++
export PATH=/opt/rh/devtoolset-8/root/usr/bin${PATH:+:${PATH}}

git clone https://github.com/weserv/images.git
cd images
mkdir build && cd build
cmake3 .. \
  -DCMAKE_BUILD_TYPE=Release
sudo make
```
