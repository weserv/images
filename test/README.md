# Test suite of weserv/images

Tests are automatically run on GitHub Actions (using
[this configuration](../.github/workflows/ci.yml)) whenever new commits are
made to the repository or when new pull requests are opened. If something
breaks, you'll be informed by GitHub.

The unit test cases are written using
[Catch2](https://github.com/catchorg/Catch2) as C++ test framework. We use the
[Test::Nginx](https://metacpan.org/pod/Test::Nginx::Socket) Perl test
scaffolding for the integration tests. Consult the previous links for
information on how to extend the tests.

## Dependencies

The test suite is run with GitHub Actions but can also be run manually, the
following dependencies are required to run the unit tests:

* libvips version >= 8.12

Other dependencies (such as [Catch2](https://github.com/catchorg/Catch2)) are
installed using the `FetchContent` module within CMake.

For the integration tests you need the following dependencies:

* Nginx version >= 1.9.11

* Perl modules:
    * [Test::Nginx](https://metacpan.org/pod/Test::Nginx::Socket)

* Nginx modules:
    * ngx_weserv (i.e., this module)

Note that Nginx is automatically configured and installed with the necessary
modules enabled using the `ExternalProject` module within CMake. If you prefer
to build Nginx by your own, use the `-DINSTALL_NGX_MODULE=OFF` on the CMake
command line.

## Code coverage

If you have LCOV installed, you can view and collect coverage information,
by specifying `-DENABLE_COVERAGE=ON` on the CMake command line. To collect
coverage information and generate a browsable html report:

```bash
cmake --build . --target coverage-html
```

You will be able to browse the LCOV report by opening `lcov/index.html`.

For only collecting collect coverage information, you can use:

```bash
cmake --build . --target coverage
```

## Unit tests

To run the unit tests without installing the Nginx module:

```bash
git clone https://github.com/weserv/images.git
cd images
mkdir build && cd build
cmake .. \
  -DCMAKE_BUILD_TYPE=Debug \
  -DBUILD_TESTS=ON \
  -DINSTALL_NGX_MODULE=OFF
cmake --build . -- -j$(nproc)
ctest -j $(nproc) --output-on-failure
```

## Integration tests

To run the integration tests in the default testing mode:

```bash
git clone https://github.com/weserv/images.git
cd images
mkdir build && cd build
cmake .. \
  -DCMAKE_BUILD_TYPE=Debug
cmake --build . -- -j$(nproc)

cd ../
export PATH="/usr/local/nginx/sbin:$PATH"
TEST_NGINX_SERVROOT="$PWD/servroot" prove -I/path/to/test-nginx/lib -r test/nginx
```

To run specific test files:

```bash
cd images
export PATH="/usr/local/nginx/sbin:$PATH"
prove -I/path/to/test-nginx/lib test/nginx/file.t test/nginx/proxy.t
```

To run a specific test block in a particular test file, add the line
`--- ONLY` to the test block you want to run, and then use the `prove`
utility to run that `.t` file.
