#!/usr/bin/env bash

version=$LUAROCKS_VERSION
luarocks_tarball=https://luarocks.github.io/luarocks/releases/luarocks-$version.tar.gz

set -e

# Do we already have the correct LuaRocks built?
if [ -d "$HOME/luarocks/bin" ]; then
    installed_version=$($HOME/luarocks/bin/luarocks --version | head -1 | awk '{print $2}')
    echo "Need LuaRocks $version"
    echo "Found LuaRocks $installed_version"

    if [ "$installed_version" = "$version" ]; then
        echo "Using cached LuaRocks directory"
        exit 0
    fi
fi

echo "Installing LuaRocks $version"

mkdir -p "$HOME/luarocks"

curl -L $luarocks_tarball | tar xz
cd luarocks-$version
./configure --prefix="$HOME/luarocks" "$@"
make -j${JOBS} && make install

# Clean-up build directory
cd ../
rm -rf luarocks-$version
