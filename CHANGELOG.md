# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.0.0] - started 2019-02-07

Requires libvips 8.8+.

See [this blog post](https://images.weserv.nl/news/2019/09/01/introducing-api-5/) for a summary of the new features in API 5.

### Added
- Support for animated WebP and GIF images.
- Support for loading HEIC-images.
- Without enlargement (`&we`, can be used in combination with all `&fit=` parameters). See [#173](https://github.com/weserv/images/issues/173) for more info.
- Background color when using `&fit=contain` (`&cbg=`).
- Image tinting (`&tint=`).
- Arbitrary rotation angles (`&ro=`).
- Background color when rotating by arbitrary angles (`&rbg=`).
- Adaptive row filtering (`&af`, PNG only).
- zlib compression level (`&l=`, PNG only).
- Metadata output (`&output=json`).
- Flipping an image horizontally (`&flop`) and/or vertically (`&flip`).
- Pre-resize crop behaviour (`&precrop`). See [#176](https://github.com/weserv/images/issues/176) for more info.
- Retrieve the largest/smallest page from a multi-resolution image (`&page=-1` / `&page=-2`). See [#170](https://github.com/weserv/images/issues/170) for more info.
- Duotone filter effect (`&filt=duotone`). The two contrasting colours can be specified with `&start=` and `&stop=`.
- Fit option to ensure that the dimensions are greater than or equal to both those specified (`&fit=outside`).
- Support for changing the `max-age` of the `Cache-Control` HTTP-header (`&maxage=`). See [#186](https://github.com/weserv/images/issues/186) for more info.

### Changed
- Rewrote the entire code base to C++.
- Improved rate limiter. See the [weserv/rate-limit-nginx-module](https://github.com/weserv/rate-limit-nginx-module) repository.
- Align the confusing transformation (`&t=`) parameters with the CSS terminology (`&fit=`).
- A JSON-formatted response with the appropriate `application/json` MIME-type, if an error occurs.
- Docker image and deployment improvements. See [#180](https://github.com/weserv/images/issues/180).

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

See [this blog post](https://images.weserv.nl/news/2018/07/29/introducing-api-4/) for a summary of the new features in API 4.

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
- `&output=webp` and `&output=tiff` in an effort to support more image formats as output. See [#68](https://github.com/weserv/images/issues/68).
- Device pixel ratio (`&dpr=`). See [#115](https://github.com/weserv/images/issues/115) for more info.
- Letterboxing (`&t=letterbox`. See [#80](https://github.com/weserv/images/issues/80) for more info.
- Rotation by any multiple of 90 (`&or=`).
- Smart crop `&a=entropy` or `&a=attention` (only works when `&t=square`).
- Focal point cropping (`&a=crop-x%-y%`, only works when `&t=square`).
- Shape cropping (`&shape=`).
- Brightness adjustment (`&bri=`).
- Contrast adjustment (`&con=`).
- Gamma adjustment (`&gam=`).
- Sharpen an image (`&sharp=`).
- Background color of an image (`&bg=`. See [#81](https://github.com/weserv/images/issues/81) for more info.
- Blur effect (`&blur=`). See [#69](https://github.com/weserv/images/issues/69).
- Filter effect (`&filt=`).
- The filename returned in the `Content-Disposition` header (`&filename=`). See [#122](https://github.com/weserv/images/issues/122) and [#78](https://github.com/weserv/images/issues/78).
- Support for Cyrillic and Arabic characters. See [#13](https://github.com/weserv/images/issues/13).
- Redirect to a default image if the image URL is not found (`&errorredirect=`). See [#37](https://github.com/weserv/images/issues/37).
- Load a given page (`&page=`, for PDF, TIFF and multi-size ICO files).
- Support for non-standard ports. See [#10](https://github.com/weserv/images/issues/10).
- A privacy policy. See [Privacy-Policy.md](Privacy-Policy.md).
- A Docker image for easier deployment. See the [Docker installation instructions](DOCKER.md).

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
This version was never used in production, it's only used for testing purposes and it was a beginning to re-write the entire image proxy (which is in production since 2007).

### Added
- Add CHANGELOG.md based on [’Keep a CHANGELOG’](https://github.com/olivierlacan/keep-a-changelog).
- Composer ready and [PSR-2](https://www.php-fig.org/psr/psr-2/) compliant.
- Used the [Intervention Image](http://image.intervention.io/) library for image handling and manipulation.
- Used the [Guzzle](https://github.com/guzzle/guzzle) library for sending HTTP requests.

## [1.0.0] - started 2007-09-10

### Note
The start of our image proxy. See for more details [here](https://github.com/weserv/images/wiki).

### Added / Changed / Fixed
We never kept a changelog from 2007 till 2015.
For a quick overview what we've added, changed or fixed in the past see our [completed label](https://github.com/weserv/images/issues?utf8=%E2%9C%93&q=label%3Acompleted%20no%3Amilestone) on our issue tracker. Or take a look at our [1.x branch](https://github.com/weserv/images/tree/1.x).

[5.0.0]: https://github.com/weserv/images/compare/4.x...5.x
[4.0.0]: https://github.com/weserv/images/compare/3.x...4.x
[3.0.0]: https://github.com/weserv/images/compare/HEAD...3.x
[2.0.0]: https://github.com/weserv/images/compare/HEAD...78d8b32
[1.0.0]: https://github.com/weserv/images/tree/1.x
