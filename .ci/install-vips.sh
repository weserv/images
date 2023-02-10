#!/usr/bin/env bash

# Define variables
version=$VIPS_VERSION
pre_version=$VIPS_PRE_VERSION
tag_version=$version${pre_version:+-$pre_version}
vips_tarball=https://github.com/libvips/libvips/releases/download/v$tag_version/vips-$version.tar.xz

# Exit immediately if a command exits with a non-zero status
set -e

# Make sure the vips folder exist
mkdir -p "$HOME/vips"

# Do we need to install vips from source?
[ "$version" = "master" ] && \
  git clone -b master --single-branch https://github.com/libvips/libvips.git vips-$version || \
  curl -Ls $vips_tarball | tar xJ

echo "Installing vips ${version/master/"from source"}"
cd vips-$version
meson setup build --prefix="$HOME/vips" --libdir=lib --buildtype=release "$@"
ninja -C build
ninja -C build install

# Clean-up build directory
cd ../
rm -rf vips-$version
