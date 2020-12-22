#!/usr/bin/env bash

# Define variables
version=$VIPS_VERSION
pre_version=$VIPS_PRE_VERSION
tag_version=$version${pre_version:+-$pre_version}
vips_tarball=https://github.com/libvips/libvips/releases/download/v$tag_version/vips-$tag_version.tar.gz

# Exit immediately if a command exits with a non-zero status
set -e

# Make sure the vips folder exist
mkdir -p "$HOME/vips"

# Do we need to install vips from source?
if [ "$version" = "master" ]; then
    echo "Installing vips from source"

    git clone -b master --single-branch https://github.com/libvips/libvips.git vips-$version
    cd vips-$version
    ./autogen.sh --prefix="$HOME/vips" "$@"
    make -j$(nproc) && make install
else
    echo "Installing vips $version"

    curl -Ls $vips_tarball | tar xz
    cd vips-$version
    ./configure --prefix="$HOME/vips" "$@"
    make -j$(nproc) && make install
fi

# Clean-up build directory
cd ../
rm -rf vips-$version
