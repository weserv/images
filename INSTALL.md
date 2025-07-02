# Install instructions (for RHEL 10, and its derivatives)

## Build dependencies

 * `cmake` >= 3.14 (for `FetchContent_MakeAvailable()` command)
 * `g++` >= 5.0 (for `-std=c++17` support)
 * `pcre2` (for nginx rewrite module)
 * `zlib` (for nginx gzip module)
 * `openssl` (for SSL support)
 * `libvips` >= 8.12

## Install instructions

```bash
# Install the EPEL repository configuration package
dnf install epel-release

# Enable the CodeReady Builder repository since EPEL packages may depend on packages from it
crb enable
# or:
# subscription-manager repos --enable "codeready-builder-for-rhel-10-$(uname -m)-rpms"

# Install the Remi repository configuration package
dnf install https://rpms.remirepo.net/enterprise/remi-release-10.rpm

# Install the RPM Fusion repository configuration package (for libheif)
dnf install --nogpgcheck https://mirrors.rpmfusion.org/free/el/rpmfusion-free-release-10.noarch.rpm

# Enable Remi's RPM repository
dnf config-manager --set-enabled remi

# Install libvips (+ development files and command-line tools)
dnf install vips vips-devel vips-tools

# Install optional modules
dnf install vips-heif vips-magick-im7 vips-poppler

# Install build requirements
dnf group install --with-optional 'Development Tools'
dnf install openssl-devel pcre2-devel zlib-devel
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
