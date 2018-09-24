# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.0.0] - started 2018-07-17

Requires libvips 8.7+ and OpenResty 1.13.6.2+.

### Note
Rewrote the complete code base to Lua and switched to [OpenResty](https://openresty.org/en/), which turns [Nginx](http://nginx.org/) and [LuaJIT](http://luajit.org/luajit.html) into a full-fledged scriptable web platform.

#### Why?
Because it's robust and fast. One of the core benefits of OpenResty is that it is fully asynchronous and that our code can be written directly inside Nginx without using PHP-FPM and FastCGI.

The amazing just-in-time (JIT) compilation and the integrated foreign function interface (FFI) in LuaJIT have also been motives to move away from PHP.

## [3.0.0] - started 2017-01-01

Requires libvips 8.7+ and PHP 7.1+.

### Note
With the feedback that you've provided we can successfully close the following issues/enhancements: 
[#10](https://github.com/weserv/images/issues/10), [#13](https://github.com/weserv/images/issues/13), [#15](https://github.com/weserv/images/issues/15), [#37](https://github.com/weserv/images/issues/37), [#62](https://github.com/weserv/images/issues/62), [#68](https://github.com/weserv/images/issues/68), [#69](https://github.com/weserv/images/issues/69), [#70](https://github.com/weserv/images/issues/70), [#75](https://github.com/weserv/images/issues/75), [#76](https://github.com/weserv/images/issues/76), [#78](https://github.com/weserv/images/issues/78), [#80](https://github.com/weserv/images/issues/80), [#81](https://github.com/weserv/images/issues/81), [#90](https://github.com/weserv/images/issues/90), [#106](https://github.com/weserv/images/issues/106), [#115](https://github.com/weserv/images/issues/115), [#121](https://github.com/weserv/images/issues/121), [#122](https://github.com/weserv/images/issues/122), [#133](https://github.com/weserv/images/issues/133), and [#137](https://github.com/weserv/images/issues/137). 
Thanks for your support!

### Added
#### Size
- Device pixel ratio `&dpr=`. See [#115](https://github.com/weserv/images/issues/115) for more info.

#### Transformation
- Letterboxing `&t=letterbox`. See [#80](https://github.com/weserv/images/issues/80) for more info.

#### Orientation
- Rotation `&or=`. Accepts `auto` or if an angle is specified, it is converted to a valid `90`/`180`/`270` degree rotation. Default is `auto`. The `auto` option uses Exif data to automatically orient images correctly.

#### Cropping
- Smart crop `&a=entropy` or `&a=attention` (only works when `&t=square`). Crops the image down to specific dimensions by removing boring parts. Where:
    - `entropy`: focus on the region with the highest [Shannon entropy](https://en.wikipedia.org/wiki/Entropy_%28information_theory%29).
    - `attention`: focus on the region with the highest luminance frequency, colour saturation and presence of skin tones.
- Focal point cropping `&a=crop-x%-y%` (only works when `&t=square`). Using two offset percentages, where `x%` is the horizontal offset and `y%` is the vertical offset.
- Shape cropping `&shape=`. Accepts:
    - `circle`
    - `ellipse`
    - `triangle`
    - `triangle-180`: Triangle tilted upside down
    - `pentagon`
    - `pentagon-180`: Pentagon tilted upside down
    - `hexagon`
    - `square`: Square tilted 45 degrees
    - `star`: 5-point star
    - `heart`

#### Adjustments
- The brightness of an image `&bri=`. Use values between `-100` and `+100`, where `0` represents no change.
- The contrast of an image `&con=`. Use values between `-100` and `+100`, where `0` represents no change.
- The gamma of an image `&gam=`. Use values between `1` and `3`. The default value is `2.2`, a suitable approximation for sRGB images.
- Sharpen an image `&sharp=`. Required format: `f,j,r`. Arguments:
    - Flat `f` - Sharpening to apply to flat areas. (Default: `1.0`)
    - Jagged `j` - Sharpening to apply to jagged areas. (Default: `2.0`)
    - Radius `r` - Sharpening mask to apply in pixels. (optional)
- The background color of an image `&bg=`. Can be used in combination with letterboxing. Accepts hexadecimal RGB and RBG alpha formats. See [#81](https://github.com/weserv/images/issues/81) for more info.

#### Effects
- The blur effect `&blur=`. Use values between `0` and `100`. See [#69](https://github.com/weserv/images/issues/69).
- The filter effect `&filt=`. Accepts `greyscale`, `sepia` or `negate`.

#### Input
- With the magical help of [libvips](https://github.com/libvips/libvips) and the PHP binding [php-vips](https://github.com/libvips/php-vips), we "officially" support `PNG`, `JPG`, `WEBP`, `GIF` (not animated), `SVG`, `PDF` and `TIFF` as image input. "Unofficially" we're supporting all [libMagick image file types](https://www.imagemagick.org/script/formats.php#supported). 

#### Output
- We've added `&output=webp` and `&output=tiff` in an effort to support more image formats as output. See [#68](https://github.com/weserv/images/issues/68).

#### Improvements
- Image filename in HTTP header (`Content-Disposition: inline`). See [#78](https://github.com/weserv/images/issues/78).
- The `&filename=` parameter to specify the filename returned in the `Content-Disposition` header. The filename must only contain alphanumeric characters. See [#122](https://github.com/weserv/images/issues/122).
- Support for Cyrillic and Arabic characters. See [#13](https://github.com/weserv/images/issues/13).
- The `&errorredirect=` parameter to redirect to a default image if the image URL is not found. The redirect URL must be formatted the same as the `?url=` parameter. See [#37](https://github.com/weserv/images/issues/37).
- In order to load a given page (for an PDF, TIFF and multi-size ICO file) we've added the `&page=` parameter. The value is numbered from zero.
- Support for non-standard ports. See [#10](https://github.com/weserv/images/issues/10).
- Added a privacy policy. See [Privacy-Policy.md](Privacy-Policy.md).
- Add support for Docker deployment. See the [Docker installation instructions](DOCKER.md).

### Changed
- Dropped [Intervention Image](http://image.intervention.io/) in favor of [php-vips](https://github.com/libvips/php-vips) because resizing an image with [libvips](https://github.com/libvips/libvips) is typically 4x-5x faster than using the quickest ImageMagick.
- We're now using the [uri package](https://github.com/thephpleague/uri) in order to parse URIs correctly. This is a drop-in replacement to PHP’s `parse_url` function.

### Deprecated
**URL-parameter values**

| GET | Value | Use instead |
| :--- | :--- | :---------- |
| `a`  | `=t` |  `=top`     |
| `a`  | `=b` |  `=bottom`  |
| `a`  | `=l` |  `=left`    |
| `a`  | `=r` |  `=right`   |

**URL-parameters**

|   GET   |  Use instead   |
| :------ | :-------------- |
| `circle` | `shape=circle` |

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
The start of our image proxy. See for more details [here](https://github.com/weserv/images/wiki/About-this-service-and-why-it-is-free).

### Added / Changed / Fixed
We never kept a changelog from 2007 till 2015.
For a quick overview what we've added, changed or fixed in the past see our [completed label](https://github.com/weserv/images/issues?utf8=%E2%9C%93&q=label%3Acompleted%20no%3Amilestone) on our issue tracker. Or take a look at our [1.x branch](https://github.com/weserv/images/tree/1.x).

[4.0.0]: https://github.com/weserv/images/compare/3.x...4.x
[3.0.0]: https://github.com/weserv/images/compare/HEAD...3.x
[2.0.0]: https://github.com/weserv/images/compare/HEAD...78d8b32
[1.0.0]: https://github.com/weserv/images/tree/1.x
