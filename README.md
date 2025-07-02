# weserv/images

[<img src="https://raw.githubusercontent.com/weserv/docs/deploy/logo.svg?sanitize=true" width="160" align="right" alt="wsrv.nl logo">][website]

[![Author](https://img.shields.io/badge/author-andrieslouw-blue.svg)][author1]
[![Author](https://img.shields.io/badge/author-kleisauke-blue.svg)][author2]
[![Source code](https://img.shields.io/badge/source-weserv/images-blue.svg)](https://github.com/weserv/images)
[![Software license](https://img.shields.io/github/license/weserv/images.svg)](https://opensource.org/licenses/BSD-3-Clause)
[![CI status](https://github.com/weserv/images/workflows/CI/badge.svg)](https://github.com/weserv/images/actions)
[![Coverage status](https://codecov.io/gh/weserv/images/graph/badge.svg)](https://codecov.io/gh/weserv/images)

Source code of wsrv.nl (formerly images.weserv.nl), to be used on your own server(s). weserv/images leverages
powerful libraries like [libvips](https://github.com/libvips/libvips) (for image handling and manipulation)
and [nginx](https://github.com/nginx/nginx) (used as web server, forward proxy and HTTP cache).

## Technologies used

- Linux, [nginx](https://github.com/nginx/nginx) and [libvips](https://github.com/libvips/libvips)
 (without these technologies, this project would never have been possible)
- [Cloudflare](https://www.cloudflare.com/) (for CDN caching and IP-blocking)
- [Redis](https://github.com/antirez/redis) (for rate limiting)
- [OpenDNS](https://www.opendns.com/) (for DNS-filtering)

## Documentation

See our [wiki documentation](https://github.com/weserv/images/wiki) or
[API reference][website] for information on using wsrv.nl.

## Docker deployment

For information on Docker deployment, please read the
[Docker installation instructions](docker/README.md).

## Submitting Bugs and Suggestions

We track support tickets, issues and feature requests using
the [GitHub issue tracker](https://github.com/weserv/images/issues).

## Credits

| [![Andries Louw Wolthuizen][avatar-author1]][author1] | [![Kleis Auke Wolthuizen][avatar-author2]][author2] |
| --- | --- |
| [Andries Louw Wolthuizen][author1] | [Kleis Auke Wolthuizen][author2] |

## License

The source code is licensed under the BSD 3-Clause License, see the [LICENSE](LICENSE) file for details.

## Privacy Policy

Please see [Privacy-Policy.md](Privacy-Policy.md) for more information.

## Last but not least

This is made in Sneek with love and passion.

[<img src="https://raw.githubusercontent.com/weserv/docs/deploy/made-in-sneek.svg?sanitize=true" height="200" alt="Made in Sneek logo">](https://en.wikipedia.org/wiki/Sneek)

[website]: https://wsrv.nl/
[author1]: https://github.com/andrieslouw
[author2]: https://github.com/kleisauke
[avatar-author1]: https://avatars.githubusercontent.com/u/11487455?v=4&s=120
[avatar-author2]: https://avatars.githubusercontent.com/u/12746591?v=4&s=120
