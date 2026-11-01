# Install instructions (for RHEL 10, and its derivatives)

## Build dependencies

 * `meson` >= 0.63 (for `preserve_path` arg in `install_headers()`)
 * `g++` >= 5.0 (for `-std=c++17` support)
 * `pcre2` (for nginx rewrite module)
 * `zlib` (for nginx gzip module)
 * `openssl` (for SSL support)
 * `libvips` >= 8.13

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
dnf install vips-heif vips-jxl vips-magick-im7 vips-poppler

# Install build requirements
dnf group install 'Development Tools'
dnf install meson openssl-devel pcre2-devel zlib-devel
```

## Build

```bash
git clone --recurse-submodules https://github.com/weserv/images.git
cd images
meson setup build --prefix=/usr
meson compile -C build
meson install -C build

mkdir nginx
curl -Ls https://nginx.org/download/nginx-1.31.8.tar.gz | \
  tar xzC nginx --strip-components=1
cd nginx
./configure --add-module=../ --with-http_ssl_module
make -j$(nproc)
make install
```
