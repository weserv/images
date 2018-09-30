#!/usr/bin/env bash

version=${VIPS_VERSION}
pre_version=${VIPS_PRE_VERSION}
tar_version=${VIPS_TAR_VERSION}
vips_tarball=https://github.com/libvips/libvips/releases/download/v${version}${pre_version:+-$pre_version}/vips-${tar_version}.tar.gz

set -e

# Do we already have the correct vips built?
if [ -d "$HOME/vips/bin" ]; then
    installed_version=$($HOME/vips/bin/vips --version | awk -F- '{print $2}')
    echo "Need vips $version"
    echo "Found vips $installed_version"

    if [[ "$installed_version" == "$version" ]]; then
        echo "Using cached vips directory"
        exit 0
    fi
fi

echo "Installing vips $version"

rm -rf $HOME/vips
mkdir $HOME/vips

curl -L ${vips_tarball} | tar xz
cd vips-${version}
CXXFLAGS=-D_GLIBCXX_USE_CXX11_ABI=0 ./configure --prefix="$HOME/vips" $*
make -j${JOBS} && make install

cd ../
rm -rf vips-${version}
