# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Current trunk] - started 2019-09-01

Requires libvips 8.9+.

### Added
- Support for true streaming ([#225](https://github.com/weserv/images/pull/225)).
- Support for upstream TLSv1.3 connections.
- Install target for the library and CLI tool.
- Support for changing the nginx version during building.
- Debug mode within the weserv module (`&debug=`). Requires nginx to be compiled with `--with-debug`.
- `pagePrimary` to metadata output (`&output=json`).
- Support for brightness, saturation and hue modulation (`&mod=`) ([#226](https://github.com/weserv/images/pull/226)).
- nginx directives for configuring API limits ([#227](https://github.com/weserv/images/issues/227)).
- Support for nginx's `proxy_pass` directive ([#251](https://github.com/weserv/images/issues/251)).
- AVIF encoding support (`&output=avif`) ([#238](https://github.com/weserv/images/issues/238)).
- Support for enabling or disabling image savers (`weserv_savers` directive).
- Prebuilt Docker image to GitHub's Container Registry ([#204](https://github.com/weserv/images/issues/204)).
- Support for specifying the default quality per format (`weserv_*_quality` directives).
- Support for controlling the CPU effort spent on improving compression (`weserv_*_effort` directives).
- The `rel="canonical"` response header to proxied images ([#309](https://github.com/weserv/images/issues/309)).
- The `Timing-Allow-Origin` response header ([#311](https://github.com/weserv/images/issues/311)).
- Alpine based Docker image ([#317](https://github.com/weserv/images/pull/317)).
- A pkg-config file of the API library (`weserv.pc`).
- Variants of nginx configurations (`imagesweserv-no-cache.conf` and `imagesweserv-secure-link.conf`).
- Support for configuring the number of worker threads of libvips (via the `VIPS_CONCURRENCY` env).
- `/clear-cache` API endpoint ([#272](https://github.com/weserv/images/issues/272)).
- `$weserv_response_length` embedded nginx variable.
- Additional response headers ([#325](https://github.com/weserv/images/issues/325)).
- Support multi-frame images for various operations (embed, crop, mask and orient).
- The `weserv_canonical_header` nginx directive ([#309](https://github.com/weserv/images/issues/309)).
- Client-side DNS failover mechanism ([#331](https://github.com/weserv/images/issues/331)).
- The `weserv_deny_ip` nginx directive.
- Support for `&default=1` ([#371](https://github.com/weserv/images/issues/371)).
- Support for percentage-based values for some parameters ([#384](https://github.com/weserv/images/issues/384)).
- Support for lossless encoding of WebP images (`&ll`) ([#386](https://github.com/weserv/images/issues/386)).

### Changed
- Migrate Docker base image to Rocky Linux 9.
- Attempt to decode corrupted or invalid images ([#194](https://github.com/weserv/images/issues/194)).
- Docker image improvements ([#215](https://github.com/weserv/images/pull/215), [#216](https://github.com/weserv/images/pull/216), [#230](https://github.com/weserv/images/pull/230) and [#283](https://github.com/weserv/images/pull/283)).
- Return an error when the maximum number of pages is exceeded ([#243](https://github.com/weserv/images/issues/243)).
- Bump minimum required libvips version to 8.9.
- Allow arbitrary values for the max-age parameter ([#292](https://github.com/weserv/images/issues/292)).
- Increase rate limit quota to 2500 requests per 10 minutes ([#196](https://github.com/weserv/images/issues/196)).
- The name of the project, CLI and shared library name (`imagesweserv` -> `weserv`).
- Warn if nginx was configured without `--with-http_ssl_module`.
- Build nginx with `--with-http_secure_link_module` by default.
- Migrate from PCRE to PCRE2.
- Modernize code to C++17.
- Use jemalloc in the glibc-based Dockerfile.
- Improve ICC profile conversion.
- Speed-up thumbnailing of RGBA images.

### Fixed
- Compatibility with CMake < 3.12.
- Compatibility with legacy websites by using lowest OpenSSL security level ([#208](https://github.com/weserv/images/issues/208)).
- Thread safety with copy-on-write for metadata ([lovell/sharp#1986](https://github.com/lovell/sharp/issues/1986)).
- A small memory leak in thumbnail.
- Comply URI parser with the RFC-3986 standard ([#237](https://github.com/weserv/images/issues/237)).
- Page height logic for non-animated images ([#242](https://github.com/weserv/images/issues/242)).
- Premultiplication bug during masking ([#245](https://github.com/weserv/images/issues/245)).
- Message for HTTP 500 response status codes ([#264](https://github.com/weserv/images/issues/264)).
- Focal point calculations ([#270](https://github.com/weserv/images/issues/270)).
- Parsing of key-value pairs ([#279](https://github.com/weserv/images/issues/279) and [#358](https://github.com/weserv/images/issues/358)).
- Reduce the size of the Docker image ([#316](https://github.com/weserv/images/issues/316)).
- Only set permanent URLs as canonical ([#309](https://github.com/weserv/images/issues/309)).
- Using the `&page` parameter in combination with `&n=-1`.
- Skip shrink-on-load for known libjpeg rounding errors.
- Compatibility with mixed C++ ABIs ([#338](https://github.com/weserv/images/issues/338)).
- Prevent upsizing via libwebp.
- Honor the `VIPS_MIN_STACK_SIZE` and `VIPS_BLOCK_UNTRUSTED` env variables.
- Ensure use of flip forces random access read.
- The maximum values of the sharpen operation ([#357](https://github.com/weserv/images/issues/357)).
- Bump buffer size for HTTP response headers ([#378](https://github.com/weserv/images/issues/378)).
- Ensure correct dimensions for 90/270 rotate.

### Deprecated
| Before               | Use instead                             |
| :------------------- | :-------------------------------------- |
| `&bri=[-100/+100]`   | `&mod=[brightness multiplier]`          |
| `&a=focal-[x%]-[y%]` | `&fpx=[x offset]` and `&fpy=[y offset]` |

### Removed
The `weserv_mode proxy|file;` directive is removed in favour of the `weserv proxy|filter;` directive. This means that
all occurrences of `weserv on|off;` and `weserv_mode proxy|file;` should be removed from existing nginx configurations.

To enable the Weserv module, it is now mandatory to include the `weserv proxy|filter;` directive in the location block.
For example:
```diff
@@ -1,12 +1,10 @@
 server {
-    weserv on;
-
     location / {
-        weserv_mode proxy;
+        weserv proxy;
     }
 
     location /static {
-        weserv_mode file;
+        weserv filter;
 
         alias /var/www/imagesweserv/public;
     }
```

## [5.0.0] - started 2019-02-07

Requires libvips 8.8+.

See [this blog post](https://wsrv.nl/news/2019/09/01/introducing-api-5/) for a summary of the new features in API 5.

### Added
- Support for animated WebP and GIF images.
- Support for loading HEIC-images.
- Without enlargement (`&we`, can be used in combination with all `&fit=` parameters) ([#173](https://github.com/weserv/images/issues/173)).
- Background color when using `&fit=contain` (`&cbg=`).
- Image tinting (`&tint=`).
- Arbitrary rotation angles (`&ro=`).
- Background color when rotating by arbitrary angles (`&rbg=`).
- Adaptive row filtering (`&af`, PNG only).
- zlib compression level (`&l=`, PNG only).
- Metadata output (`&output=json`).
- Flipping an image horizontally (`&flop`) and/or vertically (`&flip`).
- Pre-resize crop behaviour (`&precrop`) ([#176](https://github.com/weserv/images/issues/176)).
- Retrieve the largest/smallest page from a multi-resolution image (`&page=-1` / `&page=-2`) ([#170](https://github.com/weserv/images/issues/170)).
- Duotone filter effect (`&filt=duotone`). The two contrasting colours can be specified with `&start=` and `&stop=`.
- Fit option to ensure that the dimensions are greater than or equal to both those specified (`&fit=outside`).
- Support for changing the `max-age` of the `Cache-Control` HTTP-header (`&maxage=`) ([#186](https://github.com/weserv/images/issues/186)).

### Changed
- Rewrote the entire code base to C++.
- Improved rate limiter. See the [weserv/rate-limit-nginx-module](https://github.com/weserv/rate-limit-nginx-module) repository.
- Align the confusing transformation (`&t=`) parameters with the CSS terminology (`&fit=`).
- A JSON-formatted response with the appropriate `application/json` MIME-type, if an error occurs.
- Docker image and deployment improvements ([#180](https://github.com/weserv/images/issues/180)).

### Deprecated
| Before                  | Use instead                                   |
| :---------------------- | :-------------------------------------------- |
| `&t=fit`                | `&fit=inside&we`                              |
| `&t=fitup`              | `&fit=inside`                                 |
| `&t=square`             | `&fit=cover`                                  |
| `&t=squaredown`         | `&fit=cover&we`                               |
| `&t=absolute`           | `&fit=fill`                                   |
| `&t=letterbox`          | `&fit=contain`                                |
| `&a=crop-[x%]-[y%]`     | `&a=focal-[x%]-[y%]`                          |
| `&errorredirect`        | `&default`                                    |
| `&sharp=[f],[j],[s]`    | `&sharp=[s]`, `&sharpf=[f]` and `&sharpj=[j]` |
| `&crop=[w],[h],[x],[y]` | `&cw=[w]`, `&ch=[h]`, `&cx=[x]` and `&cy=[y]` |

## [4.0.0] - started 2018-07-17

Requires libvips 8.7+ and OpenResty 1.13.6.2+.

See [this blog post](https://wsrv.nl/news/2018/07/29/introducing-api-4/) for a summary of the new features in API 4.

### Added
- Mask background (`&mbg=`).

### Changed
- Rewrote the complete code base to Lua and switched to [OpenResty](https://openresty.org/en/).
    - Switch from libvips' [PHP binding](https://github.com/libvips/php-vips) to the [LuaJIT binding](https://github.com/libvips/lua-vips).
- The `&shape` and `&strim` parameters were renamed to `&mask` and `&mtrim`.
- Support for URI's starting with `http://` and `https://`.

### Deprecated
| Before    | Use instead |
| :-------- | :---------- |
| `&shape=` | `&mask=`    |
| `&strim=` | `&mtrim=`   |

## [3.0.0] - started 2017-01-01

Requires libvips 8.7+ and PHP 7.1+.

### Note
With the magical help of [libvips](https://github.com/libvips/libvips) and the PHP binding [php-vips](https://github.com/libvips/php-vips), we "officially" support `PNG`, `JPG`, `WEBP`, `GIF` (not animated), `SVG`, `PDF` and `TIFF` as image input. "Unofficially" we're supporting all [libMagick image file types](https://www.imagemagick.org/script/formats.php#supported).

### Added
- `&output=webp` and `&output=tiff` in an effort to support more image formats as output ([#68](https://github.com/weserv/images/issues/68)).
- Device pixel ratio (`&dpr=`) ([#115](https://github.com/weserv/images/issues/115)).
- Letterboxing (`&t=letterbox`) ([#80](https://github.com/weserv/images/issues/80)).
- Rotation by any multiple of 90 (`&or=`).
- Smart crop `&a=entropy` or `&a=attention` (only works when `&t=square`).
- Focal point cropping (`&a=crop-x%-y%`, only works when `&t=square`).
- Shape cropping (`&shape=`).
- Brightness adjustment (`&bri=`).
- Contrast adjustment (`&con=`).
- Gamma adjustment (`&gam=`).
- Sharpen an image (`&sharp=`).
- Background color of an image (`&bg=`) ([#81](https://github.com/weserv/images/issues/81)).
- Blur effect (`&blur=`) ([#69](https://github.com/weserv/images/issues/69)).
- Filter effect (`&filt=`).
- The filename returned in the `Content-Disposition` header (`&filename=`) ([#122](https://github.com/weserv/images/issues/122) and [#78](https://github.com/weserv/images/issues/78)).
- Support for Cyrillic and Arabic characters ([#13](https://github.com/weserv/images/issues/13)).
- Redirect to a default image if the image URL is not found (`&errorredirect=`) ([#37](https://github.com/weserv/images/issues/37)).
- Load a given page (`&page=`, for PDF, TIFF and multi-size ICO files).
- Support for non-standard ports ([#10](https://github.com/weserv/images/issues/10)).
- A privacy policy. See [Privacy-Policy.md](Privacy-Policy.md).
- A Docker image for easier deployment. See the [Docker installation instructions](docker/README.md).

### Changed
- Dropped [Intervention Image](http://image.intervention.io/) in favor of [php-vips](https://github.com/libvips/php-vips) because resizing an image with [libvips](https://github.com/libvips/libvips) is typically 4x-5x faster than using the quickest ImageMagick.
- We're now using the [uri package](https://github.com/thephpleague/uri) in order to parse URIs correctly. This is a drop-in replacement to PHP’s `parse_url` function.

### Deprecated
| Before    | Use instead     |
| :-------- | :-------------- |
| `&a=t`    | `&a=top`        |
| `&a=b`    | `&a=bottom`     |
| `&a=l`    | `&a=left`       |
| `&a=r`    | `&a=right`      |
| `&circle` | `&shape=circle` |

## [2.0.0] - started 2015-12-27

### Note
This version was never used in production, it's only used for testing purposes, and it was a beginning to re-write the entire image proxy (which is in production since 2007).

### Added
- Add CHANGELOG.md based on [’Keep a CHANGELOG’](https://github.com/olivierlacan/keep-a-changelog).
- Composer ready and [PSR-2](https://www.php-fig.org/psr/psr-2/) compliant.
- Used the [Intervention Image](http://image.intervention.io/) library for image handling and manipulation.
- Used the [Guzzle](https://github.com/guzzle/guzzle) library for sending HTTP requests.

## [1.0.0] - started 2007-09-10

### Note
The start of our image proxy. See for more details [here](https://github.com/weserv/images/wiki).

### Added / Changed / Fixed
We never kept a changelog from 2007 to 2015.
For a quick overview what we've added, changed or fixed in the past see our [completed label](https://github.com/weserv/images/issues?utf8=%E2%9C%93&q=label%3Acompleted%20no%3Amilestone) on our issue tracker. Or take a look at our [1.x branch](https://github.com/weserv/images/tree/1.x).

[Current trunk]: https://github.com/weserv/images/compare/v5.0.0...HEAD
[5.0.0]: https://github.com/weserv/images/compare/4.x...v5.0.0
[4.0.0]: https://github.com/weserv/images/compare/3.x...4.x
[3.0.0]: https://github.com/weserv/images/compare/78d8b32...3.x
[2.0.0]: https://github.com/weserv/images/compare/6524ee1...78d8b32
[1.0.0]: https://github.com/weserv/images/compare/1.x...6524ee1
