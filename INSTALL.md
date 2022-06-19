# Install instructions (for RHEL 8, and it's derivatives)

## Build dependencies

 * `cmake` >= 3.11 (for `FetchContent` module)
 * `g++` >= 5.0 (for `-std=c++17` support)
 * `pcre2` (for nginx rewrite module)
 * `zlib` (for nginx gzip module)
 * `openssl` (for SSL support)
 * `libvips` >= 8.9

## Install instructions

```bash
# Install the EPEL repository configuration package
dnf install epel-release

# Install the Remi repository configuration package
dnf install https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Install the RPM Fusion repository configuration package (for libheif)
dnf install --nogpgcheck https://mirrors.rpmfusion.org/free/el/rpmfusion-free-release-8.noarch.rpm

# Install the DNF plugins package (for the dnf config-manager command)
dnf install dnf-plugins-core

# Enable Remi's RPM repository
dnf config-manager --set-enabled remi

# Enable the PowerTools repository since EPEL packages may depend on packages from it
dnf config-manager --set-enabled powertools

# Install libvips 8.12 (full-fat version)
dnf install vips-devel vips-heif vips-magick-im6

# Install build requirements
dnf install \
  autoconf \
  automake \
  cmake \
  make \
  gcc \
  gcc-c++ \
  git \
  glibc-devel \
  glibc-headers \
  openssl-devel \
  pcre2-devel \
  zlib-devel
```

## Build

```bash
git clone --recurse-submodules https://github.com/weserv/images.git
cd images
mkdir build && cd build
cmake .. \
  -DCMAKE_BUILD_TYPE=Release
sudo cmake --build . -- -j$(nproc)
```
