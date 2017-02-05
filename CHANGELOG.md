# Changelog

All notable changes to this project will be documented in this file.

## [3.0.0] - 2017-02-05

- Dropped [Intervention Image](http://image.intervention.io/) in favor of [php-vips](https://github.com/jcupitt/php-vips) because resizing an image with [libvips](https://github.com/jcupitt/libvips) is typically 4x-5x faster than using the quickest ImageMagick.
- Updated dependencies.

## [2.0.0] - 2015-12-27

- Add CHANGELOG.md based on [’Keep a CHANGELOG’](https://github.com/olivierlacan/keep-a-changelog)
- Composer ready and [PSR-2](http://www.php-fig.org/psr/psr-2/) compliant
- Used the [Intervention Image](http://image.intervention.io/) library for image handling and manipulation
- Used the [Guzzle](https://github.com/guzzle/guzzle) library for sending HTTP requests
- [New features](https://images.weserv.nl/#quick-reference)

[2.0.0]: https://github.com/andrieslouw/imagesweserv/releases/tag/2.0.0
[3.0.0]: https://github.com/andrieslouw/imagesweserv/releases/tag/3.0.0