#!/usr/bin/env bash

# Define default arguments
from_source=false
use_cache=true
configure_args=()

# Parse arguments
for i in "$@"; do
    case $1 in
        --from-source) from_source=true ;;
        --skip-cache) use_cache=false ;;
        --) shift; configure_args+=("$@"); break ;;
        *) configure_args+=("$1") ;;
    esac
    shift
done

# Define variables
version=${VIPS_VERSION}
pre_version=${VIPS_PRE_VERSION}
tar_version=${VIPS_TAR_VERSION}
vips_tarball=https://github.com/libvips/libvips/releases/download/v${version}${pre_version:+-$pre_version}/vips-${tar_version:-$version}.tar.gz

# Exit immediately if a command exits with a non-zero status
set -e

# Do we already have the correct vips built?
if [[ -d "$HOME/vips/bin" ]]; then
    installed_version=$($HOME/vips/bin/vips --version | awk -F- '{print $2}')
    echo "Need vips $version"
    echo "Found vips $installed_version"

    if [[ "$installed_version" == "$version" ]] && [[ "$use_cache" = true ]]; then
        echo "Using cached vips directory"
        exit 0
    fi
fi

# Make sure the vips folder exist
if [[ ! -d "$HOME/vips" ]]; then
    mkdir "$HOME/vips"
fi

# Do we need to install vips from source?
if [[ "$from_source" = true ]]; then
    echo "Installing vips from source"

    git clone -b master --single-branch https://github.com/libvips/libvips.git vips-${version}
    cd vips-${version}
    ./autogen.sh --prefix="$HOME/vips" "${configure_args[@]}"
    make -j${JOBS} && make install
else
    echo "Installing vips $version"

    curl -L ${vips_tarball} | tar xz
    cd vips-${version}
    ./configure --prefix="$HOME/vips" "${configure_args[@]}"
    make -j${JOBS} && make install
fi

# Clean-up build directory
cd ../
rm -rf vips-${version}
