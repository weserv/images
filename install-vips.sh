#!/bin/bash

version=$VIPS_VERSION
pre_version=$VIPS_PRE_VERSION
tar_version=$VIPS_TAR_VERSION
vips_tarball=https://github.com/jcupitt/libvips/releases/download/v$version${pre_version:+-$pre_version}/vips-$tar_version.tar.gz

set -e

# do we already have the correct vips built? early exit if yes
# we could check the configure params as well I guess
if [ -d "$HOME/vips/bin" ]; then
  installed_version=$($HOME/vips/bin/vips --version)
  escaped_version=${version//./\\.}
  echo "Need vips-$version"
  echo "Found $installed_version"
  if [[ "$installed_version" =~ ^vips-$escaped_version ]]; then
    echo "Using cached directory"
    #exit 0
  fi
fi

rm -rf $HOME/vips
wget $vips_tarball
tar xf vips-$tar_version.tar.gz
cd vips-$version
CXXFLAGS=-D_GLIBCXX_USE_CXX11_ABI=0 ./configure --prefix=$HOME/vips $*
make && make install
