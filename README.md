# Images.weserv.nl

<img src="https://raw.githubusercontent.com/weserv/docs/deploy/logo.svg?sanitize=true" width="160" height="160" alt="Images.weserv.nl logo" align="right">

[![Author](https://img.shields.io/badge/author-andrieslouw-blue.svg)](https://github.com/andrieslouw)
[![Author](https://img.shields.io/badge/author-kleisauke-blue.svg)](https://github.com/kleisauke)
[![Source Code](https://img.shields.io/badge/source-weserv/images-blue.svg)](https://github.com/weserv/images)
[![Software License](https://img.shields.io/github/license/weserv/images.svg)](https://opensource.org/licenses/BSD-3-Clause)
[![Build Status](https://travis-ci.org/weserv/images.svg?branch=5.x)](https://travis-ci.org/weserv/images)
[![Coverage Status](https://codecov.io/gh/weserv/images/branch/5.x/graph/badge.svg)](https://codecov.io/gh/weserv/images)

Source code of images.weserv.nl, to be used on your own server(s). 
Images.weserv.nl leverages powerful libraries like [libvips](https://github.com/libvips/libvips) 
(for image handling and manipulation) and [nginx](https://github.com/nginx/nginx) (used as web server, forward proxy and HTTP cache).

## Technologies used

- Linux, [nginx](https://github.com/nginx/nginx), [libvips](https://github.com/libvips/libvips) 
(without these technologies,  this project would never have been possible)
- [Cloudflare](https://www.cloudflare.com/) (for CDN caching and IP-blocking)
- [Redis](https://github.com/antirez/redis) (for rate limiting)
- [OpenDNS](https://www.opendns.com/) (for DNS-filtering)

## Documentation

See our [wiki documentation](https://github.com/weserv/images/wiki) or 
[API reference](https://images.weserv.nl/) for information on using images.weserv.nl.

## Docker deployment

For information on Docker deployment, please read the 
[Docker installation instructions](DOCKER.md).

## Submitting Bugs and Suggestions

We track support tickets, issues and feature requests using 
the [GitHub issue tracker](https://github.com/weserv/images/issues).

## Credits

[![Andries Louw Wolthuizen][avatar-author1]](https://github.com/andrieslouw) | [![Kleis Auke Wolthuizen][avatar-author2]](https://github.com/kleisauke)
------------- | -------------
[Andries Louw Wolthuizen](https://github.com/andrieslouw) | [Kleis Auke Wolthuizen](https://github.com/kleisauke)

## License

The source code is licensed under the BSD 3-Clause License, see the [LICENSE](LICENSE) file for details.

## Privacy Policy

Please see [Privacy-Policy.md](Privacy-Policy.md) for more information.

## Last but not least

This is made in Sneek with love and passion.

<a href="https://en.wikipedia.org/wiki/Sneek"><img src="https://raw.githubusercontent.com/weserv/docs/deploy/made-in-sneek.svg?sanitize=true" height="200" alt="Made in Sneek logo"></a>

[avatar-author1]: https://avatars2.githubusercontent.com/u/11487455?v=3&s=120
[avatar-author2]: https://avatars2.githubusercontent.com/u/12746591?v=3&s=120
