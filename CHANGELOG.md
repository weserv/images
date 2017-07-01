# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Note
With the feedback that you've provided we can successfully close the following issues/enhancements: 
[#13](https://github.com/andrieslouw/imagesweserv/issues/13), [#15](https://github.com/andrieslouw/imagesweserv/issues/15), [#37](https://github.com/andrieslouw/imagesweserv/issues/37), [#62](https://github.com/andrieslouw/imagesweserv/issues/62), [#68](https://github.com/andrieslouw/imagesweserv/issues/68), [#69](https://github.com/andrieslouw/imagesweserv/issues/69), [#70](https://github.com/andrieslouw/imagesweserv/issues/70), [#75](https://github.com/andrieslouw/imagesweserv/issues/75), [#76](https://github.com/andrieslouw/imagesweserv/issues/76), [#78](https://github.com/andrieslouw/imagesweserv/issues/78), [#80](https://github.com/andrieslouw/imagesweserv/issues/80), [#81](https://github.com/andrieslouw/imagesweserv/issues/81) and [#90](https://github.com/andrieslouw/imagesweserv/issues/90). 
Thanks for your support!

### Added
#### Transformation
- Letterboxing `&t=letterbox`. See [#80](https://github.com/andrieslouw/imagesweserv/issues/80) for more info.

#### Orientation
- Rotation `&or=`. Accepts `auto`, `0`, `90`, `180` or `270`. Default is `auto`. The `auto` option uses Exif data to automatically orient images correctly.

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

#### Adjustments
- The brightness of an image `&bri=`. Use values between `-100` and `+100`, where `0` represents no change.
- The contrast of an image `&con=`. Use values between `-100` and `+100`, where `0` represents no change.
- The gamma of an image `&gam=`. Use values between `1` and `3`. The default value is `2.2`, a suitable approximation for sRGB images.
- Sharpen an image `&sharp=`. Required format: `f,j,r`. Arguments:
    - Flat `f` - Sharpening to apply to flat areas. (Default: `1.0`)
    - Jagged `j` - Sharpening to apply to jagged areas. (Default: `2.0`)
    - Radius `r` - Sharpening mask to apply in pixels. (optional)
- The background color of an image `&bg=`. Can be used in combination with letterboxing. Accepts hexadecimal RGB and RBG alpha formats. See [#81](https://github.com/andrieslouw/imagesweserv/issues/81) for more info.

#### Effects
- The blur effect `&blur=`. Use values between `0` and `100`. See [#69](https://github.com/andrieslouw/imagesweserv/issues/69).
- The filter effect `&filt=`. Accepts `greyscale`, `sepia` or `negate`.

#### Input
- With the magical help of [libvips](https://github.com/jcupitt/libvips) and the PHP binding [php-vips](https://github.com/jcupitt/php-vips), we "officially" support `PNG`, `JPG`, `WEBP`, `GIF` (not animated), `SVG`, `PDF` and `TIFF` as image input. "Unofficially" we're supporting all [libMagick image file types](https://www.imagemagick.org/script/formats.php#supported). 

#### Output
- We've added `&output=webp` in an effort to support more image formats as output. See [#68](https://github.com/andrieslouw/imagesweserv/issues/68).

#### Improvements
- Image filename in HTTP header (`Content-Disposition: inline`). See [#78](https://github.com/andrieslouw/imagesweserv/issues/78).
- Support for Cyrillic and Arabic characters. See [#13](https://github.com/andrieslouw/imagesweserv/issues/13).
- The `&errorredirect=` parameter to redirect to a default image if the image URL is not found. The redirect URL must be formatted the same as the `?url=` parameter. See [#37](https://github.com/andrieslouw/imagesweserv/issues/37).
- In order to load a given page (for an PDF, TIFF and multi-size ICO file) we've added the `&page=` parameter. The value is numbered from zero.

### Changed
- Dropped [Intervention Image](http://image.intervention.io/) in favor of [php-vips](https://github.com/jcupitt/php-vips) because resizing an image with [libvips](https://github.com/jcupitt/libvips) is typically 4x-5x faster than using the quickest ImageMagick.
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

## [2.0.0] - 2015-12-27
### Note
This version was never used in production, it's only used for testing purposes and it was a beginning to re-write the entire image proxy (which is in production since 2007).

### Added
- Add CHANGELOG.md based on [’Keep a CHANGELOG’](https://github.com/olivierlacan/keep-a-changelog).
- Composer ready and [PSR-2](http://www.php-fig.org/psr/psr-2/) compliant.
- Used the [Intervention Image](http://image.intervention.io/) library for image handling and manipulation.
- Used the [Guzzle](https://github.com/guzzle/guzzle) library for sending HTTP requests.

## [1.0.0] - 2007-09-10
### Note
The start of our image proxy. See for more details [here](https://github.com/andrieslouw/imagesweserv/wiki/About-this-service-and-why-it-is-free).

### Added / Changed / Fixed
We never kept a change log from 2007 till 2015.
For a quick overview what we've added, changed or fixed in the past see our [completed label](https://github.com/andrieslouw/imagesweserv/issues?utf8=%E2%9C%93&q=label%3Acompleted%20no%3Amilestone) on our issue tracker. Or take a look at our [1.x branch](https://github.com/andrieslouw/imagesweserv/tree/1.x).

[Unreleased]: https://github.com/andrieslouw/imagesweserv/compare/HEAD...3.x
[2.0.0]: https://github.com/andrieslouw/imagesweserv/compare/HEAD...78d8b32
[1.0.0]: https://github.com/andrieslouw/imagesweserv/tree/1.x