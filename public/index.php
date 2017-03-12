<?php
/**
 * Source code of images.weserv.nl, to be used on your own server(s).
 *
 * PHP version 7
 *
 * @category  Images
 * @package   Imagesweserv
 * @author    Andries Louw Wolthuizen <info@andrieslouw.nl>
 * @author    Kleis Auke Wolthuizen   <info@kleisauke.nl>
 * @license   http://opensource.org/licenses/bsd-license.php New BSD License
 * @link      images.weserv.nl
 * @copyright 2017
 */

error_reporting(E_ALL);
set_time_limit(180);
ini_set('display_errors', 0);

require __DIR__ . '/../vendor/autoload.php';

use AndriesLouw\imagesweserv\Exception\ImageNotReadableException;
use AndriesLouw\imagesweserv\Exception\ImageNotValidException;
use AndriesLouw\imagesweserv\Exception\ImageTooBigException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Exception\RateExceededException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use GuzzleHttp\Exception\RequestException;
use Jcupitt\Vips\Exception as VipsException;
use League\Uri\Components\HierarchicalPath as Path;
use League\Uri\Components\Query;
use League\Uri\Schemes\Http as HttpUri;

$error_messages = [
    'invalid_url' => [
        'header' => '404 Not Found',
        'content-type' => 'text/plain',
        'message' => 'Error 404: Server couldn\'t parse the ?url= that you were looking for, because it isn\'t a valid url.',
    ],
    'invalid_image' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'message' => 'The request image is not a valid (supported) image. Supported images are: %s',
        'log' => 'Non-supported image. URL: %s',
    ],
    'image_too_big' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'message' => 'The image is too big to be downloaded.' . PHP_EOL . 'Image size %s'
            . PHP_EOL . 'Max image size: %s',
        'log' => 'Image too big. URL: %s',
    ],
    'curl_error' => [
        'header' => '404 Not Found',
        'content-type' => 'text/html',
        'message' => 'Error 404: Server couldn\'t parse the ?url= that you were looking for, error it got: The requested URL returned error: %s',
        'log' => 'cURL Request error: %s URL: %s',
    ],
    'dns_error' => [
        'header' => '410 Gone',
        'content-type' => 'text/plain',
        'message' => 'Error 410: Server couldn\'t parse the ?url= that you were looking for, because the hostname of the origin is unresolvable (DNS) or blocked by policy.',
        'log' => 'cURL Request error: %s URL: %s',
    ],
    'rate_exceeded' => [
        'header' => '429 Too Many Requests',
        'content-type' => 'text/plain',
        'message' => 'There are an unusual number of requests coming from this IP address.',
    ],
    'image_not_readable' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'log' => 'Image not readable. URL: %s Message: %s',
    ],
    'image_too_large' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'message' => 'Image is too large for processing. Width x Height should be less than 70 megapixels.',
        'log' => 'Image too large. URL: %s',
    ],
    'libvips_error' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'log' => 'libvips error. URL: %s Message: %s',
    ],
    'unknown' => [
        'header' => '500 Internal Server Error',
        'content-type' => 'text/plain',
        'message' => 'Something\'s wrong!' . PHP_EOL .
            'It looks as though we\'ve broken something on our system.' . PHP_EOL .
            'Don\'t panic, we are fixing it! Please come back in a while.. ',
        'log' => 'URL: %s, Message: %s, Instance: %s',
    ]
];

/**
 * Create a new HttpUri instance from a string.
 * The string must comply with the following requirements:
 *  - Starting without 'http:' or 'https:'
 *  - HTTPS origin hosts must be prefixed with 'ssl:'
 *  - Valid according RFC3986 and RFC3987
 *
 * @param string $url
 *
 * @throws InvalidArgumentException if the URI is invalid
 * @throws League\Uri\Schemes\UriException if the URI is in an invalid state according to RFC3986
 *
 * @return HttpUri parsed URI
 */
$parseUrl = function (string $url) {
    // Check for HTTPS origin hosts
    if (substr($url, 0, 4) === 'ssl:') {
        return HttpUri::createFromString('https://' . ltrim(substr($url, 4), '/'));
    } else {
        // Check if a valid URL is given. Therefore starting without 'http:' or 'https:'.
        if (substr($url, 0, 5) !== 'http:' && substr($url, 0, 6) !== 'https:') {
            return HttpUri::createFromString('http://' . ltrim($url, '/'));
        } else {
            // Not a valid URL; throw InvalidArgumentException
            throw new InvalidArgumentException('Invalid URL');
        }
    }
};

/**
 * Sanitize the 'errorredirect' GET variable after parsing.
 * The HttpUri instance must comply with the following requirements:
 *  - Must not include a 'errorredirect' querystring (if it does, it will be ignored)
 *
 * @param HttpUri $errorUrl
 *
 * @return string sanitized URI
 */
$sanitizeErrorRedirect = function (HttpUri $errorUrl) {
    $queryStr = $errorUrl->getQuery();
    if (!empty($queryStr)) {
        $query = new Query($queryStr);
        if ($query->hasPair('errorredirect')) {
            $newQuery = $query->withoutPairs(['errorredirect']);
            return $errorUrl->withQuery($newQuery->__toString())->__toString();
        }
    }
    return $errorUrl->__toString();
};

if (!empty($_GET['url'])) {
    try {
        $uri = $parseUrl($_GET['url']);
    } catch (Exception $e) {
        $error = $error_messages['invalid_url'];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: ' . $error['content-type']);
        echo $error['message'];
        die;
    }

    // Get (potential) extension from path
    $extension = (new Path($uri->getPath()))->getExtension() ?? 'png';

    // Create a unique file (starting with 'imo_') in our shared memory
    $tmpFileName = tempnam('/dev/shm', 'imo_');

    // We need to add the extension to the temporary file for certain image types.
    // This ensures that the image is correctly recognized.
    if ($extension === 'svg' || $extension === 'ico') {
        // Rename our unique file
        rename($tmpFileName, $tmpFileName .= '.' . $extension);
    }

    // Create an PHP HTTP client
    $client = new AndriesLouw\imagesweserv\Client($tmpFileName, [
        // User agent for this client
        'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/7.0; +http://images.weserv.nl/)',
        // Float describing the number of seconds to wait while trying to connect to a server.
        // Use 0 to wait indefinitely.
        'connect_timeout' => 5,
        // Float describing the timeout of the request in seconds. Use 0 to wait indefinitely.
        'timeout' => 10,
        // Integer describing the max image size to receive (in bytes). Use 0 for no limits.
        'max_image_size' => 0,
        // Integer describing the maximum number of allowed redirects.
        'max_redirects' => 10,
        // Allowed mime types. Use empty array to allow all mime types
        'allowed_mime_types' => [
            /*'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',*/
        ]
    ]);

    /*$throttlingPolicy = new AndriesLouw\imagesweserv\Throttler\ThrottlingPolicy([
        'ban_time' => 60, // If exceed, ban for 60 minutes
        'cloudflare' => [
            'enabled' => true, // Is CloudFlare enabled?
            'email' => '',
            'auth_key' => '',
            'zone_id' => '',
            'mode' => 'block', // The action to apply if the IP get's banned
        ],
    ]);*/

    /*$memcached = new Memcached('mc');

    // When using persistent connections, it's important to not re-add servers.
    if (!count($memcached->getServerList())) {
        $memcached->setOptions([
            Memcached::OPT_BINARY_PROTOCOL => true,
            Memcached::OPT_COMPRESSION => false
        ]);

        $memcached->addServer('/var/run/memcached/memcached.sock', 11211, 0);
    }

    // Create an new Memcached throttler instance
    $throttler = new AndriesLouw\imagesweserv\Throttler\MemcachedThrottler($memcached, $throttlingPolicy, [
        'allowed_requests' => 700, // 700 allowed requests
        'minutes' => 3, // In 3 minutes
        'prefix' => 'c', // Cache key prefix
    ]);*/

    /*$redis = new Predis\Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);

    // Create an new Redis throttler instance
    $throttler = new AndriesLouw\imagesweserv\Throttler\RedisThrottler($redis, $throttlingPolicy, [
        'allowed_requests' => 700, // 700 allowed requests
        'minutes'  => 3, // In 3 minutes
        'prefix' => 'c', // Cache key prefix
    ]);*/

    $throttler = null;

    // Set manipulators
    $manipulators = [
        new AndriesLouw\imagesweserv\Manipulators\Crop(),
        new AndriesLouw\imagesweserv\Manipulators\Trim(),
        new AndriesLouw\imagesweserv\Manipulators\Size(71000000),
        new AndriesLouw\imagesweserv\Manipulators\Orientation(),
        new AndriesLouw\imagesweserv\Manipulators\Shape,
        new AndriesLouw\imagesweserv\Manipulators\Brightness(),
        new AndriesLouw\imagesweserv\Manipulators\Contrast(),
        new AndriesLouw\imagesweserv\Manipulators\Gamma(),
        new AndriesLouw\imagesweserv\Manipulators\Sharpen(),
        new AndriesLouw\imagesweserv\Manipulators\Filter(),
        new AndriesLouw\imagesweserv\Manipulators\Blur(),
        new AndriesLouw\imagesweserv\Manipulators\Background(),
    ];

    // Set API
    $api = new AndriesLouw\imagesweserv\Api\Api($client, $throttler, $manipulators);

    // Setup server
    $server = new AndriesLouw\imagesweserv\Server(
        $api
    );

    /*$server->setDefaults([
        'output' => 'png'
    ]);
    $server->setPresets([
        'small' = [
            'w' => 200,
            'h' => 200,
            'fit' => 'crop',
        ],
        'medium' = [
            'w' => 600,
            'h' => 400,
            'fit' => 'crop',
        ]
    ]);*/

    try {
        /**
         * Generate and output image.
         */
        $server->outputImage($uri, $_GET);
    } catch (ImageTooLargeException $e) {
        $error = $error_messages['image_too_large'];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: ' . $error['content-type']);
        echo $error['message'];
    } catch (RequestException $e) {
        $previousException = $e->getPrevious();

        // Check if there is a previous exception
        if ($previousException instanceof ImageNotValidException
            || $previousException instanceof ImageTooBigException
        ) {
            $clientOptions = $client->getOptions();

            if ($previousException instanceof ImageNotValidException) {
                $error = $error_messages['invalid_image'];
                $supportedImages = array_pop($clientOptions['allowed_mime_types']);

                if (count($clientOptions['allowed_mime_types']) > 1) {
                    $supportedImages = implode(', ', $clientOptions['allowed_mime_types']) . ' and ' . $supportedImages;
                }

                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
                header('Content-type: ' . $error['content-type']);

                trigger_error(sprintf($error['log'], $uri->__toString()), E_USER_WARNING);

                echo sprintf($error['message'], $supportedImages);
            } else {
                $error = $error_messages['image_too_big'];
                $imageSize = $previousException->getMessage();
                $maxImageSize = Utils::formatSizeUnits($clientOptions['max_image_size']);

                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
                header('Content-type: ' . $error['content-type']);

                trigger_error(sprintf($error['log'], $uri->__toString()), E_USER_WARNING);

                echo sprintf($error['message'], $imageSize, $maxImageSize);
            }
        } else {
            $curlHandler = $e->getHandlerContext();

            $isDnsError = isset($curlHandler['errno']) && $curlHandler['errno'] === 6;

            $error = $isDnsError ? $error_messages['dns_error'] : $error_messages['curl_error'];

            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
            header('Content-type: ' . $error['content-type']);

            $statusCode = $e->getCode();
            $reasonPhrase = $e->getMessage();

            if ($e->hasResponse() && $e->getResponse() !== null) {
                $statusCode = $e->getResponse()->getStatusCode();
                $reasonPhrase = $e->getResponse()->getReasonPhrase();
            }

            $errorMessage = "$statusCode $reasonPhrase";

            if (!$isDnsError && isset($_GET['errorredirect'])) {
                $isSameHost = 'weserv.nl';

                try {
                    $uri = $parseUrl($_GET['errorredirect']);

                    $append = substr($uri->getHost(), -strlen($isSameHost)) === $isSameHost ? "&error=$statusCode" : '';

                    $sanitizedUri = $sanitizeErrorRedirect($uri);

                    header('Location: ' . $sanitizedUri . $append);
                } catch (Exception $ignored) {
                    $message = sprintf($error['message'], $errorMessage);

                    echo $message;
                }
            } else {
                $message = $isDnsError ? $error['message'] : sprintf($error['message'], $errorMessage);

                echo $message;
            }
        }
    } catch (RateExceededException $e) {
        $error = $error_messages['rate_exceeded'];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: ' . $error['content-type']);
        echo $error['header'] . ' - ' . $error['message'];
    } catch (ImageNotReadableException $e) {
        $error = $error_messages['image_not_readable'];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: ' . $error['content-type']);

        echo $error['header'] . ' - ' . $e->getMessage();
    } catch (VipsException $e) {
        $error = $error_messages['libvips_error'];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: ' . $error['content-type']);

        // Log libvips exceptions
        trigger_error(
            sprintf(
                $error['log'],
                $uri->__toString(),
                $e->getMessage()
            ),
            E_USER_WARNING
        );

        echo $error['header'] . ' - ' . $e->getMessage();
    } catch (Exception $e) {
        // If there's an exception which is not already caught.
        // Then it's a unknown exception.
        $error = $error_messages['unknown'];

        // Log unknown exceptions
        trigger_error(
            sprintf(
                $error['log'],
                $uri->__toString(),
                $e->getMessage(),
                get_class($e)
            ),
            E_USER_WARNING
        );

        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: ' . $error['content-type']);
        echo $error['message'];
    }

    // Still here? Unlink the temporary file.
    @unlink($tmpFileName);
} else {
    $config = [
        'name' => 'API 3 - GitHub, DEMO',
        'url' => 'images.weserv.nl',
        'exampleImage' => 'rbx.weserv.nl/lichtenstein.jpg',
        'exampleTransparentImage' => 'rbx.weserv.nl/transparency_demo.png'
    ];

    if (file_exists(__DIR__ . '/../config.php')) {
        $config = include(__DIR__ . '/../config.php');
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"/>
    <title>Image cache &amp; resize proxy</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico"/>
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/github-fork-ribbon-css/0.2.0/gh-fork-ribbon.min.css" integrity="sha384-HoFyoZ18hzctAGKv3BkTqlVH019/pvkU+3BKRAlDRgLuguzWZI0MMuF0JpcMOAye" crossorigin="anonymous" />
    <link rel="stylesheet" href="//static.weserv.nl/images-v3b.css" integrity="sha384-uK5EDEl5284pk7sp8AC3YxS4EqJ6yZO8/yT4sCB8iwG2As8Fv9QJA2l7leomLlbQ" crossorigin="anonymous">
	<!--[if lte IE 9]>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/github-fork-ribbon-css/0.2.0/gh-fork-ribbon.ie.min.css" />
	<script src="//static.weserv.nl/html5shiv-printshiv.min.js" type="text/javascript"></script>
	<![endif]-->
    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js" type="text/javascript" integrity="sha384-8gBf6Y4YYq7Jx97PIqmTwLPin4hxIzQw5aDmUg/DDhul9fFpbbLcLh3nTIIDJKhx" crossorigin="anonymous"></script>
    <script src="//static.weserv.nl/bootstrap.min.js" type="text/javascript" integrity="sha384-knhWhSzIcpYIfitKUjDBo/EQ3F5MWCwASUtB6UCe2N038X5KhwbGAoxmLaV8hn12" crossorigin="anonymous"></script>
</head>
<body data-spy="scroll" data-target=".scrollspy">
    <nav id="sidebar">
        <div id="header-wrapper">
            <div id="header">
                <a id="logo" href="//{$config['url']}/">
                    <div id="weserv-logo">Images.<strong>weserv</strong>.nl</div>
                    <span>Image cache &amp; resize proxy</span>
                </a>
            </div>
        </div>
        <div class="scrollbar-inner">
            <div class="scrollspy">
                <ul id="nav" class="nav topics" data-spy="affix">
                    <li class="dd-item active"><a href="#image-api" class="cen"><span>{$config['name']}</span></a>
                        <ul class="nav inner">
                            <li class="dd-item"><a href="#quick-reference"><span>Quick reference</span></a></li>
                            <li class="dd-item"><a href="#size"><span>Size</span></a></li>
                            <li class="dd-item"><a href="#orientation"><span>Orientation</span></a></li>
                            <li class="dd-item"><a href="#trans"><span>Transformation</span></a></li>
                            <li class="dd-item"><a href="#crop"><span>Crop position</span></a></li>
                            <li class="dd-item"><a href="#shape"><span>Shape</span></a></li>
                            <li class="dd-item"><a href="#adjustments"><span>Adjustments</span></a></li>
                            <li class="dd-item"><a href="#effects"><span>Effects</span></a></li>
                            <li class="dd-item"><a href="#encoding"><span>Encoding</span></a></li>
                        </ul>
                    </li>
                </ul>
                <br />
                <section id="footer">
                    <p><a href="https://github.com/andrieslouw/imagesweserv">Source code available on GitHub</a><br /><a href="//getgrav.org">Design inspired by Grav</a></p>
                </section>
            </div>
        </div>
    </nav>
    <section id="body">
        <div class="highlightable">
            <div id="body-inner">
                <section id="image-api" class="goto">
                    <p>Images.<b>weserv</b>.nl is an image <b>cache</b> &amp; <b>resize</b> proxy. Our servers resize your image, cache it worldwide, and display it. <a class="github-fork-ribbon right-top" href="https://github.com/andrieslouw/imagesweserv/issues" title="Feedback? Github!">Feedback? GitHub!</a></p>
                    <ul>
                        <li>We don't support animated images (yet), but we do support GIF, JPEG, PNG, BMP, XBM, WebP and other filetypes, even transparent images.</li>
                        <li>We do support IPv6, <a href="http://ipv6-test.com/validate.php?url={$config['url']}" rel="nofollow">serving dual stack</a>, and supporting <a href="https://{$config['url']}/?url=ipv6.google.com/logos/logo.gif">IPv6-only origin hosts</a>.</li>
                        <li>For secure connections over TLS/SSL, you can use <a href="https://{$config['url']}/"><b>https</b>://{$config['url']}/</a>. <br /><small class="sslnote">This can be very useful for embedding HTTP images on HTTPS websites. HTTPS origin hosts can be used by <a href="https://github.com/andrieslouw/imagesweserv/issues/33">prefixing the hostname with ssl:</a></small></li>
                        <li>We're part of the <a href="https://www.cloudflare.com/">Cloudflare</a> community. Images are being cached and delivered straight from <a href="https://www.cloudflare.com/network-map">100+ global datacenters</a>. This ensures the fastest load times and best performance.</li>
                        <li>On average, we resize 1 million (10<sup>6</sup>) images per hour, which generates around 25TB of outbound traffic per month.</li>
                    </ul>
                    <p>Requesting an image:</p>
                    <ul>
                        <li><code>?url=</code> (URL encoded) link to your image, without http://</li>
                    </ul>
                </section>
                <section id="quick-reference" class="goto">
                    <h1>Quick reference</h1>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>GET</th>
                                <th>Description</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Width</td>
                                <td><code>w</code></td>
                                <td>Sets the width of the image, in pixels.</td>
                                <td><a href="#width-w">info</a></td>
                            </tr>
                            <tr>
                                <td>Height</td>
                                <td><code>h</code></td>
                                <td>Sets the height of the image, in pixels.</td>
                                <td><a href="#height-h">info</a></td>
                            </tr>
                            <tr>
                                <td>Orientation</td>
                                <td><code>or</code></td>
                                <td>Rotates the image.</td>
                                <td><a href="#orientation-or">info</a></td>
                            </tr>
                            <tr>
                                <td>Transformation</td>
                                <td><code>t</code></td>
                                <td>Sets how the image is fitted to its target dimensions.</td>
                                <td><a href="#trans-fit">info</a></td>
                            </tr>
                            <tr>
                                <td>Crop</td>
                                <td><code>crop</code></td>
                                <td>Crops the image to specific dimensions.</td>
                                <td><a href="#crop-crop">info</a></td>
                            </tr>
                            <tr>
                                <td>Crop alignment</td>
                                <td><code>a</code></td>
                                <td>Sets how the crop is aligned.</td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td>Shape</td>
                                <td><code>shape</code></td>
                                <td>Crops the image to a specific shape.</td>
                                <td><a href="#shape-shape">info</a></td>
                            </tr>
                            <tr>
                                <td>Brightness</td>
                                <td><code>bri</code></td>
                                <td>Adjusts the image brightness.</td>
                                <td><a href="#brightness-bri">info</a></td>
                            </tr>
                            <tr>
                                <td>Contrast</td>
                                <td><code>con</code></td>
                                <td>Adjusts the image contrast.</td>
                                <td><a href="#contrast-con">info</a></td>
                            </tr>
                            <tr>
                                <td>Gamma</td>
                                <td><code>gam</code></td>
                                <td>Adjusts the image gamma.</td>
                                <td><a href="#gamma-gam">info</a></td>
                            </tr>
                            <tr>
                                <td>Sharpen</td>
                                <td><code>sharp</code></td>
                                <td>Sharpen the image.</td>
                                <td><a href="#sharpen-sharp">info</a></td>
                            </tr>
                            <tr>
                                <td>Trim</td>
                                <td><code>trim</code></td>
                                <td>Trim "boring" pixels from all edges.</td>
                                <td><a href="#trim-trim">info</a></td>
                            </tr>
                            <tr>
                                <td>Blur</td>
                                <td><code>blur</code></td>
                                <td>Adds a blur effect to the image.</td>
                                <td><a href="#blur-blur">info</a></td>
                            </tr>
                            <tr>
                                <td>Filter</td>
                                <td><code>filt</code></td>
                                <td>Applies a filter effect to the image.</td>
                                <td><a href="#filter-filt">info</a></td>
                            </tr>
                            <tr>
                                <td>Background</td>
                                <td><code>bg</code></td>
                                <td>Sets the background color of the image.</td>
                                <td><a href="#background-bg">info</a></td>
                            </tr>
                            <tr>
                                <td>Quality</td>
                                <td><code>q</code></td>
                                <td>Defines the quality of the image.</td>
                                <td><a href="#quality-q">info</a></td>
                            </tr>
                            <tr>
                                <td>Output</td>
                                <td><code>output</code></td>
                                <td>Encodes the image to a specific format.</td>
                                <td><a href="#output-output">info</a></td>
                            </tr>
                            <tr>
                                <td>Interlace / progressive</td>
                                <td><code>il</code></td>
                                <td>Adds interlacing to GIF and PNG. JPEG's become progressive.</td>
                                <td><a href="#interlace-progressive-il">info</a></td>
                            </tr>
                            <tr>
                                <td>Base64 (data URL)</td>
                                <td><code>encoding</code></td>
                                <td>Encodes the image to be used directly in the src= of the &lt;img&gt;-tag.</td>
                                <td><a href="#base64-encoding">info</a></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section id="size" class="goto">
                    <h1>Size</h1>
                    <h3 id="width-w">Width <code>&amp;w=</code></h3>
                    <p>Sets the width of the image, in pixels.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300" alt=""/></a>
                    <h3 id="height-h">Height <code>&amp;h=</code></h3>
                    <p>Sets the height of the image, in pixels.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;h=300"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;h=300"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;h=300" alt=""/></a>
                </section>
                <section id="orientation" class="goto">
                    <h1>Orientation</h1>
                    <h3 id="orientation-or">Orientation <code>&amp;or=</code> <span class="new">New!</span></h3>
                    <p>Rotates the image. Accepts <code>auto</code>, <code>0</code>, <code>90</code>, <code>180</code> or <code>270</code>. Default is <code>auto</code>. The <code>auto</code> option uses Exif data to automatically orient images correctly.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;h=300&amp;or=90"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;h=300&amp;or=90"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;h=300&amp;or=90" alt=""/></a>
                </section>
                <section id="trans" class="goto">
                    <h1>Transformation <code>&amp;t=</code></h1>
                    <p>Sets how the image is fitted to its target dimensions. Below are a couple of examples.</p>
                    <h3 id="trans-fit">Fit <code>&amp;t=fit</code></h3>
                    <p>Default. Resizes the image to fit within the width and height boundaries without cropping, distorting or altering the aspect ratio. <b>Will not</b> oversample the image if the requested size is larger than that of the original.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=fit"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=fit"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=fit" alt=""/></a>
                    <h3 id="trans-fitup">Fitup <code>&amp;t=fitup</code></h3>
                    <p>Resizes the image to fit within the width and height boundaries without cropping, distorting or altering the aspect ratio. <b>Will</b> increase the size of the image if it is smaller than the output size.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=fitup"&gt;</code></pre>
                    <h3 id="trans-square">Square <code>&amp;t=square</code></h3>
                    <p>Resizes the image to fill the width and height boundaries and crops any excess image data. The resulting image will match the width and height constraints without distorting the image. <b>Will</b> increase the size of the image if it is smaller than the output size.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square" alt=""/></a>
                     <h3 id="trans-squaredown">Squaredown <code>&amp;t=squaredown</code></h3>
                    <p>Resizes the image to fill the width and height boundaries and crops any excess image data. The resulting image will match the width and height constraints without distorting the image. <b>Will not</b> oversample the image if the requested size is larger than that of the original.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=squaredown"&gt;</code></pre>
                    <h3 id="trans-absolute">Absolute <code>&amp;t=absolute</code></h3>
                    <p>Stretches the image to fit the constraining dimensions exactly. The resulting image will fill the dimensions, and will not maintain the aspect ratio of the input image.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=absolute"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=absolute"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=absolute" alt=""/></a>
                    <h3 id="trans-letterbox">Letterbox <code>&amp;t=letterbox</code> <span class="new">New!</span></h3>
                    <p>Resizes the image to fit within the width and height boundaries without cropping or distorting the image, and the remaining space is filled with the background color. The resulting image will match the constraining dimensions.</p><p>More info: <a href="https://github.com/andrieslouw/imagesweserv/issues/80">Issue #80 - letterbox images that need to fit</a>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=letterbox&amp;bg=black"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=letterbox&amp;bg=black"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=letterbox&amp;bg=black" alt=""/></a>
                </section>
                <section id="crop" class="goto">
                    <h1 id="crop-position">Crop position <code>&amp;a=</code></h1>
                    <p>You can also set where the image is cropped by adding a crop position. Only works when <code>t=square</code>. Accepts <code>top</code>, <code>left</code>, <code>center</code>, <code>right</code> or <code>bottom</code>. Default is <code>center</code>. For more information, please see the suggestion on our GitHub issue tracker: <a href="https://github.com/andrieslouw/imagesweserv/issues/24">Issue #24 - Aligning</a>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;a=top"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;a=top"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;a=top" alt=""/></a>
                    <h3 id="crop-focal-point">Crop Focal Point <span class="new">New!</span></h3>
                    <p>In addition to the crop position, you can be more specific about the exact crop position using a focal point. Only works when <code>t=square</code>. This is defined using two offset percentages: <code>crop-x%-y%</code>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;a=crop-25-45"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;a=crop-25-45"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;a=crop-25-45" alt=""/></a>
                    <h3 id="crop-crop">Manual crop <code>&amp;crop=</code></h3>
                    <p>Crops the image to specific dimensions prior to any other resize operations. Required format: <code>width,height,x,y</code>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;crop=300,300,680,500"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;crop=300,300,680,500"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;crop=300,300,680,500" alt=""/></a>
                </section>
                <section id="shape" class="goto">
                    <h1>Shape</h1>
                    <h3 id="shape-shape">Shape <code>&amp;shape=</code></h3>
                    <p>Crops the image to a specific shape. More info: <a href="https://github.com/andrieslouw/imagesweserv/issues/49">Issue #49 - Add circle effect to photos</a>.</p>
                    <h4 id="shape-accepts">Accepts:</h4>
                    <ul>
                        <li><code>circle</code></li>
                        <li><code>ellipse</code></li>
                        <li><code>triangle</code></li>
                        <li><code>triangle-180</code>: Triangle tilted upside down</li>
                        <li><code>pentagon</code></li>
                        <li><code>pentagon-180</code>: Pentagon tilted upside down</li>
                        <li><code>hexagon</code></li>
                        <li><code>square</code>: Square tilted 45 degrees</li>
                        <li><code>star</code>: 5-point star</li>
                    </ul>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle" alt=""/></a>
                </section>
                <section id="adjustments" class="goto">
                    <h1>Adjustments</h1>
                    <h3 id="brightness-bri">Brightness <code>&amp;bri=</code> <span class="new">New!</span></h3>
                    <p>Adjusts the image brightness. Use values between <code>-100</code> and <code>+100</code>, where <code>0</code> represents no change.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;bri=-25"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;bri=-25"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;bri=-25" alt=""/></a>
                    <h3 id="contrast-con">Contrast <code>&amp;con=</code> <span class="new">New!</span></h3>
                    <p>Adjusts the image contrast. Use values between <code>-100</code> and <code>+100</code>, where <code>0</code> represents no change.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;con=25"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;con=25"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;con=25" alt=""/></a>
                    <h3 id="gamma-gam">Gamma <code>&amp;gam=</code> <span class="new">New!</span></h3>
                    <p>Adjusts the image gamma. Use values between <code>1</code> and <code>3</code>. The default value is <code>2.2</code>, a suitable approximation for sRGB images.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;gam=3"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;gam=3"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;gam=3" alt=""/></a>
                    <h3 id="sharpen-sharp">Sharpen <code>&amp;sharp=</code> <span class="new">New!</span></h3>
                    <p>Sharpen the image. Required format: <code>f,j,r</code></p>
                    <h4 id="sharpen-arguments">Arguments:</h4>
                    <ul>
                        <li>Flat <code>f</code> - Sharpening to apply to flat areas. (Default: 1.0)</li>
                        <li>Jagged <code>j</code> - Sharpening to apply to jagged areas. (Default: 2.0)</li>
                        <li>Radius <code>r</code> - Sharpening mask to apply in pixels, but comes at a performance cost. (optional)</li>
                    </ul>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;sharp=5,5,3"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;sharp=5,5,3"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;sharp=5,5,3" alt=""/></a>
                    <h3 id="trim-trim">Trim <code>&amp;trim=</code></h3>
                    <p>Trim "boring" pixels from all edges that contain values within a similarity of the top-left pixel. Trimming occurs before any resize operation. Use values between <code>0</code> and <code>255</code> to define a tolerance level to trim away similar color values. You also can specify just &trim, which defaults to a tolerance level of 10.</p><p>More info: <a href="https://github.com/andrieslouw/imagesweserv/issues/39">Issue #39 - able to remove black/white whitespace</a>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleTransparentImage']}&amp;w=300&amp;trim=10"&gt;</code></pre>
                    <a class="trimedges" href="//{$config['url']}/?url={$config['exampleTransparentImage']}&amp;w=300&amp;trim=10"><img src="//{$config['url']}/?url={$config['exampleTransparentImage']}&amp;w=300&amp;trim=10" alt=""/></a>
                    <h3 id="background-bg">Background <code>&amp;bg=</code> <span class="new">New!</span></h3>
                    <p>Sets the background color of the image. Supports a variety of color formats. In addition to the 140 color names supported by all modern browsers (listed <a href="//{$config['url']}/colors.html">here</a>), it also accepts hexadecimal RGB and RBG alpha formats.</p>
                    <h4 id="hexadecimal">Hexadecimal</h4>
                    <ul>
                        <li>3 digit RGB: <code>CCC</code></li>
                        <li>4 digit ARGB (alpha): <code>5CCC</code></li>
                        <li>6 digit RGB: <code>CCCCCC</code></li>
                        <li>8 digit ARGB (alpha): <code>55CCCCCC</code></li>
                    </ul>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleTransparentImage']}&amp;w=400&amp;bg=black"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleTransparentImage']}&amp;w=400&amp;bg=black"><img src="//{$config['url']}/?url={$config['exampleTransparentImage']}&amp;w=400&amp;bg=black" alt=""/></a>
                </section>
                <section id="effects" class="goto">
                    <h1>Effects</h1>
                    <h3 id="blur-blur">Blur <code>&amp;blur=</code> <span class="new">New!</span></h3>
                    <p>Adds a blur effect to the image. Use values between <code>0</code> and <code>100</code>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;blur=5"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;blur=5"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;blur=5" alt=""/></a>
                    <h3 id="filter-filt">Filter <code>&amp;filt=</code> <span class="new">New!</span></h3>
                    <p>Applies a filter effect to the image. Accepts <code>greyscale</code> or <code>sepia</code>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;filt=greyscale"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;filt=greyscale"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;filt=greyscale" alt=""/></a>
                </section>
                <section id="encoding" class="goto">
                    <h1>Encoding</h1>
                    <h3 id="quality-q">Quality <code>&amp;q=</code></h3>
                    <p>Defines the quality of the image. Use values between <code>0</code> and <code>100</code>. Defaults to <code>85</code>. Only relevant if the format is set to <code>jpg</code>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;q=20"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;q=20"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;q=20" alt=""/></a>
                    <h3 id="output-output">Output <code>&amp;output=</code></h3>
                    <p>Encodes the image to a specific format. Accepts <code>jpg</code>, <code>png</code>, <code>gif</code> or <code>webp</code>. If none is given, it will honor the origin image format.</p><p>More info: <a href="https://github.com/andrieslouw/imagesweserv/issues/62">Issue #62 - Format conversion</a>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;output=webp"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;output=webp"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;output=webp" alt=""/></a>
                    <h3 id="interlace-progressive-il">Interlace / progressive <code>&amp;il</code></h3>
                    <p>Adds interlacing to GIF and PNG. JPEG's become progressive.</p><p>More info: <a href="https://github.com/andrieslouw/imagesweserv/issues/50">Issue #50 - Add parameter to use progressive JPEGs</a>.</p>
                    <pre><code class="language-html">&lt;img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;il"&gt;</code></pre>
                    <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;il"><img src="//{$config['url']}/?url={$config['exampleImage']}&amp;w=300&amp;il" alt=""/></a>
                    <h3 id="base64-encoding">Base64 (data URL) <code>&amp;encoding=base64</code></h3>
                    <p>Encodes the image to be used directly in the src= of the <code>&lt;img&gt;</code>-tag. <a href="//{$config['url']}/?url={$config['exampleImage']}&amp;crop=100,100,680,500&amp;encoding=base64">Use this link to see the output result</a>.</p><p>More info: <a href="https://github.com/andrieslouw/imagesweserv/issues/59">Issue #59 - Return image base64 encoded</a>.</p>
                    <pre><code>//{$config['url']}/?url={$config['exampleImage']}&amp;crop=100,100,680,500&amp;encoding=base64</code></pre>
                </section>
            </div>
        </div>
    </section>
</body>
</html>
HTML;
    echo $html;
}