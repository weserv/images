# Install instructions (for CentOS 8)

## Build dependencies

 * `cmake` >= 3.11
 * `g++` => 5.0
 * `libpcre3` (for nginx rewrite module)
 * `openssl` (for nginx ssl support)
 * `libvips` >= 8.9

## Install instructions

```bash
# Install the EPEL repository configuration package
dnf install epel-release

# Install the Remi repository configuration package
dnf install https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Install the RPM Fusion repository configuration package (for libheif)
dnf install --nogpgcheck https://download1.rpmfusion.org/free/el/rpmfusion-free-release-8.noarch.rpm

# Install the dnf-utils package (for the dnf config-manager command)
dnf install dnf-utils

# Enable Remi's RPM repository
dnf config-manager --set-enabled remi

# Enable the PowerTools repository since EPEL packages may depend on packages from it
dnf config-manager --set-enabled PowerTools

# Install libvips 8.10 (full-fat version)
yum install vips-full-devel

# Install build requirements
yum install \
  autoconf \
  automake \
  cmake3 \
  make \
  gcc \
  gcc-c++ \
  git \
  glibc-devel \
  glibc-headers \
  openssl-devel \
  pcre-devel \
  zlib-devel
```

## Build

```bash
git clone --recurse-submodules https://github.com/weserv/images.git
cd images
mkdir build && cd build
cmake3 .. \
  -DCMAKE_BUILD_TYPE=Release
sudo make
```
