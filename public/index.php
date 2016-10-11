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
 * @copyright 2016
 */

error_reporting(E_ALL);
set_time_limit(180);
ini_set('display_errors', 0);

require __DIR__ . '/../vendor/autoload.php';

use AndriesLouw\imagesweserv\Exception\ImageProcessingException;
use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use GuzzleHttp\Exception\RequestException;
use League\Uri\Schemes\Http as HttpUri;

$error_messages = [
    'invalid_url' => [
        'header' => '404 Not Found',
        'content-type' => 'text/plain',
        'message' => 'Error 404: Invalid url.',
        'log' => 'URL failed, unable to parse. URL: %s',
    ],
    'invalid_image' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'message' => 'The request image is not a valid (supported) image. Supported images are: %s',
        'log' => 'Non-supported image. URL: %s',
    ],
    'too_big_image' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'message' => 'The image is too big to be downloaded.' . PHP_EOL . 'Image size %s'
            . PHP_EOL . 'Max image size: %s',
        'log' => 'Image too big. URL: %s',
    ],
    'too_large_image' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'message' => 'Image is too large for processing. Width x Height should be less than 70 megapixels.',
        'log' => 'Image too large. URL: %s',
    ],
    'curl_error' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
        'message' => 'cURL Request error: %s Status code: %s',
        'log' => 'cURL Request error: %s URL: %s Status code: %s',
    ],
    'processing_error' => [
        'header' => '400 Bad Request',
        'content-type' => 'text/plain',
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


if (!empty($_GET['url'])) {
    try {
        if (substr($_GET['url'], 0, 4) == 'ssl:') {
            $_GET['url'] = substr($_GET['url'], 4);
            $uri = HttpUri::createFromString('https://' . ltrim($_GET['url'], '/'));
        } else {
            if (substr($_GET['url'], 0, 5) != 'http:' && substr($_GET['url'], 0, 6) != 'https:') {
                $uri = HttpUri::createFromString('http://' . ltrim($_GET['url'], '/'));
            } else {
                throw new InvalidArgumentException('Invalid URL');
            }
        }
    } catch (Exception $e) {
        $error = $error_messages['invalid_url'];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: ' . $error['content-type']);
        echo $error['message'];
        trigger_error(sprintf($error['log'], $_GET['url']), E_USER_WARNING);
        die;
    }

    $extension = $uri->path->getExtension() ?? 'png';

    $sysFileName = tempnam('/dev/shm', 'imo_');

    // We need to add the extension to the temp file.
    // This ensures that the image is correctly recognized.
    $tmpFileName = $sysFileName . '.' . $extension;

    @link($sysFileName, $tmpFileName);

    unlink($sysFileName);

    // Create an PHP HTTP client
    $client = new AndriesLouw\imagesweserv\Client($tmpFileName, [
        // User agent for this client
        'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/6.0; +http://images.weserv.nl/)',
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
        ],
        'error_message' => $error_messages,
    ]);

    // Set manipulators
    $manipulators = [
        new AndriesLouw\imagesweserv\Manipulators\Orientation(),
        new AndriesLouw\imagesweserv\Manipulators\Crop(),
        new AndriesLouw\imagesweserv\Manipulators\Trim(),
        new AndriesLouw\imagesweserv\Manipulators\Size(71000000),
        new AndriesLouw\imagesweserv\Manipulators\Shape,
        new AndriesLouw\imagesweserv\Manipulators\Brightness(),
        //new AndriesLouw\imagesweserv\Manipulators\Contrast(),
        new AndriesLouw\imagesweserv\Manipulators\Gamma(),
        new AndriesLouw\imagesweserv\Manipulators\Sharpen(),
        new AndriesLouw\imagesweserv\Manipulators\Filter(),
        new AndriesLouw\imagesweserv\Manipulators\Blur(),
        new AndriesLouw\imagesweserv\Manipulators\Background(),
    ];

    // Set API
    $api = new AndriesLouw\imagesweserv\Api\Api($client, $manipulators);

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
         * Get the image
         *
         * @var array [
         *      'image' => *Manipulated image binary data*,
         *      'type' => *The mimetype*,
         *      'extension' => *The extension*
         * ]
         */
        $image = $server->outputImage($uri->__toString(), $extension, $_GET);
    } catch (ImageTooLargeException $e) {
        $error = $error_messages['too_large_image'];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: ' . $error['content-type']);
        echo $error['message'];
        die;
    } catch (RequestException $e) {
        $previousException = $e->getPrevious();

        // Check if there is a previous exception
        if ($previousException != null) {
            if ($previousException instanceof AndriesLouw\imagesweserv\Exception\ImageNotValidException) {
                $error = $error_messages['invalid_image'];
                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
                header('Content-type: ' . $error['content-type']);
                echo $previousException->getMessage();
            } else {
                if ($previousException instanceof AndriesLouw\imagesweserv\Exception\ImageTooBigException) {
                    $error = $error_messages['too_big_image'];
                    header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
                    header('Content-type: ' . $error['content-type']);
                    echo $previousException->getMessage();
                } else {
                    $error = $error_messages['unknown'];
                    header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
                    header('Content-type: ' . $error['content-type']);
                    echo $error['message'];
                }
            }
        } else {
            $error = $error_messages['curl_error'];
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
            header('Content-type: ' . $error['content-type']);
            echo $e->getMessage();
        }
        die;
    } catch (ImageProcessingException $e) {
        $error = $error_messages['processing_error'];
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $error['header']);
        header('Content-type: text/plain');
        echo $error['header'] . ' - ' . $e->getMessage();
        die;
    }

    // Still here? All checks are valid so echo te response
    header('Expires: ' . date_create('+31 days')->format('D, d M Y H:i:s') . ' GMT'); //31 days
    header('Cache-Control: max-age=2678400'); //31 days

    if (array_key_exists('encoding', $_GET) && $_GET['encoding'] == 'base64') {
        $base64 = sprintf('data:%s;base64,%s', $image['type'], base64_encode($image['image']));

        header('Content-type: text/plain');

        echo $base64;
    } else {
        header('Content-type: text/plain');
        header('Content-type: ' . $image['type']);

        $friendlyName = pathinfo($uri->path->getBasename(), PATHINFO_FILENAME) . '.' . $image['extension'];

        if (array_key_exists('download', $_GET)) {
            header('Content-Disposition: attachment; filename="' . $friendlyName . '"');
        } else {
            header('Content-Disposition: inline; filename="' . $friendlyName . '"');
        }

        ob_start();
        echo $image['image'];
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
    }

    unlink($tmpFileName);
    exit;
} else {
    $url = '//images.weserv.nl';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"/>
    <title>Image cache &amp; resize proxy</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico"/>
    <link href="//static.weserv.nl/images-v2.css" type="text/css" rel="stylesheet" integrity="sha384-Hbsu1aa2We8FHR6UVE0dG6SPY/JzwDukp+uWCpgR+Qkcai6cDzvItzPkyvto6Gai" crossorigin="anonymous"/>
    <!--[if lte IE 9]><script src="//static.weserv.nl/html5shiv-printshiv.min.js" type="text/javascript"></script><![endif]-->
    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js" type="text/javascript" integrity="sha384-8gBf6Y4YYq7Jx97PIqmTwLPin4hxIzQw5aDmUg/DDhul9fFpbbLcLh3nTIIDJKhx" crossorigin="anonymous"></script>
    <script src="//static.weserv.nl/bootstrap.min.js" type="text/javascript" integrity="sha384-knhWhSzIcpYIfitKUjDBo/EQ3F5MWCwASUtB6UCe2N038X5KhwbGAoxmLaV8hn12" crossorigin="anonymous"></script>
    <!-- TODO: Place this inside the images-v2.css -->
    <style type="text/css">
    .new {
        display: inline-block;
        line-height: 1;
        vertical-align: middle;
        margin-left: .5rem;
        margin-right: .14285714em;
        background-color: #a72376;
        border: 0 solid #a72376;
        padding: .5833em .833em;
        color: #fff;
        text-transform: none;
        font-weight: 600;
        font-size: .85714286rem;
        border-radius: .28571429rem;
    }
    h3 > .new {
        margin-top: -.29165em;
    }
    h1 > .new {
        margin-top: -.39165em;
    }
</style>
</head>
<body data-spy="scroll" data-target=".scrollspy">
    <nav id="sidebar">
        <div id="header-wrapper">
            <div id="header">
                <a id="logo" href="//images.weserv.nl/">
                    <div id="weserv-logo">Images.<strong>weserv</strong>.nl</div>
                    <span>Image cache &amp; resize proxy</span>
                </a>
            </div>
        </div>
        <div class="scrollbar-inner">
            <div class="scrollspy">
                <ul id="nav" class="nav topics" data-spy="affix">
                    <li class="dd-item active"><a href="#image-api" class="cen"><span>API 2 - RBX, FR</span></a>
                        <ul class="nav inner">
                            <li class="dd-item"><a href="#deprecated"><span>Deprecated</span></a></li>
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
                    <p>Images.<b>weserv</b>.nl is an image <b>cache</b> &amp; <b>resize</b> proxy. Our servers resize your image, cache it worldwide, and display it.</p>
                    <ul>
                        <li>We don't support animated images (yet).</li>
                        <li>We do support GIF, JPEG, PNG, BMP, XBM, WebP and other filetypes!</li>
                        <li>We do support transparent images.</li>
                        <li>We do support IPv6, <a href="http://ipv6-test.com/validate.php?url=images.weserv.nl" rel="nofollow">serving dual stack</a>, and supporting <a href="https://images.weserv.nl/?url=ipv6.google.com/logos/logo.gif">IPv6-only origin hosts</a>.</li>
                        <li>We do support SSL, you can use <a href="https://images.weserv.nl/"><b>https</b>://images.weserv.nl/</a>.
                            <br /><small class="sslnote">This can be very useful for embedding HTTP images on HTTPS websites. HTTPS origin hosts can be used by <a href="https://imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/2693328-add-support-to-fetch-images-over-https">prefixing the hostname with ssl:</a></small></li>
                    </ul>
                    <p>We're part of the <a href="https://www.cloudflare.com/">CloudFlare</a> community. Images are being cached and delivered straight from <a href="https://www.cloudflare.com/network-map">80+ global datacenters</a>. This ensures the fastest load times and best performance. On average, we resize 1 million (10<sup>6</sup>) images per hour, which generates around 25TB of outbound traffic per month.</p>
                    <p>Requesting an image:</p>
                    <ul>
                        <li><code>?url=</code> (URL encoded) link to your image, without http://</li>
                    </ul>
                </section>
                <section id="deprecated" class="goto">
                    <h1>Deprecated</h1>
                    <div class="notices warning">
                        <p>In January 2016 we introduced Version 2 of the Images.weserv.nl API. To make room for new improvements some parameters will be changed in the future.<br/>We also kept Version 1 (which is in place since December 2010) of the API  in place so as not to break anyone's apps. Please update your code to use the changed API parameters.</p>
                    </div>
                    <h2 id="deprecated-values">Deprecated URL-parameter values</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>GET</th>
                                <th>Value</th>
                                <th>Use instead</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>a</code></td>
                                <td><code style="color:red;">=t</code></td>
                                <td><code style="color:green;">=top</code></td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td><code>a</code></td>
                                <td><code style="color:red;">=b</code></td>
                                <td><code style="color:green;">=bottom</code></td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td><code>a</code></td>
                                <td><code style="color:red;">=l</code></td>
                                <td><code style="color:green;">=left</code></td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td><code>a</code></td>
                                <td><code style="color:red;">=r</code></td>
                                <td><code style="color:green;">=right</code></td>
                                <td><a href="#crop-position">info</a></td>
                            </tr>
                            <tr>
                                <td><code>trim</code></td>
                                <td><code style="color:red;">=sensitivity between 0 and 255</code></td>
                                <td><code style="color:green;">=percentaged tolerance level between 0 and 100</code></td>
                                <td><a href="#trim-trim">info</a></td>
                            </tr>
                            <tr>
                                <td><code>output</code></td>
                                <td><code style="color:red;">=gif</code></td>
                                <td><code style="color:green;">=jpg, =png or =webp</code></td>
                                <td><a href="#output-output">info</a></td>
                            </tr>
                        </tbody>
                    </table>
                    <h2 id="deprecated-functions">Deprecated URL-parameters</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>GET</th>
                                <th>Use instead</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code style="color:red;">circle</code></td>
                                <td><code style="color:green;">shape=circle</code></td>
                                <td><a href="#shape">info</a></td>
                            </tr>
                        </tbody>
                    </table>
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
                            <!-- <tr>
                                <td>Contrast</td>
                                <td><code>con</code></td>
                                <td>Adjusts the image contrast.</td>
                                <td><a href="#contrast-con">info</a></td>
                            </tr> -->
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
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300" alt=""/></a>
                    <h3 id="height-h">Height <code>&amp;h=</code></h3>
                    <p>Sets the height of the image, in pixels.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300" alt=""/></a>
                </section>
                <section id="orientation" class="goto">
                    <h1>Orientation <span class="new">New!</span></h1>
                    <h3 id="orientation-or">Orientation <code>or</code></h3>
                    <p>Rotates the image. Accepts <code>auto</code>, <code>0</code>, <code>90</code>, <code>180</code> or <code>270</code>. Default is <code>auto</code>. The <code>auto</code> option uses Exif data to automatically orient images correctly.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300&amp;or=90"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300&amp;or=90"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;h=300&amp;or=90" alt=""/></a>
                </section>
                <section id="trans" class="goto">
                    <h1>Transformation <code>&amp;t=</code></h1>
                    <p>Sets how the image is fitted to its target dimensions. Below are a couple of examples.</p>
                    <h3 id="trans-fit">Fit <code>&amp;t=fit</code></h3>
                    <p>Default. Resizes the image to fit within the width and height boundaries without cropping, distorting or altering the aspect ratio. <b>Will not</b> oversample the image if the requested size is larger than that of the original.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=fit"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=fit"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=fit" alt=""/></a>
                    <h3 id="trans-fitup">Fitup <code>&amp;t=fitup</code></h3>
                    <p>Resizes the image to fit within the width and height boundaries without cropping, distorting or altering the aspect ratio. <b>Will</b> increase the size of the image if it is smaller than the output size.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=fitup"&gt;</code></pre>
                    <h3 id="trans-square">Square <code>&amp;t=square</code></h3>
                    <p>Resizes the image to fill the width and height boundaries and crops any excess image data. The resulting image will match the width and height constraints without distorting the image. <b>Will</b> increase the size of the image if it is smaller than the output size.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square" alt=""/></a>
                     <h3 id="trans-squaredown">Squaredown <code>&amp;t=squaredown</code></h3>
                    <p>Resizes the image to fill the width and height boundaries and crops any excess image data. The resulting image will match the width and height constraints without distorting the image. <b>Will not</b> oversample the image if the requested size is larger than that of the original.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=squaredown"&gt;</code></pre>
                    <h3 id="trans-absolute">Absolute <code>&amp;t=absolute</code></h3>
                    <p>Stretches the image to fit the constraining dimensions exactly. The resulting image will fill the dimensions, and will not maintain the aspect ratio of the input image.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=absolute"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=absolute"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=absolute" alt=""/></a>
                    <h3 id="trans-letterbox">Letterbox <code>&amp;t=letterbox</code> <span class="new">New!</span></h3>
                    <p>Resizes the image to fit within the width and height boundaries without cropping or distorting the image, and the remaining space is filled with the background color. The resulting image will match the constraining dimensions.</p><p>More info: <a href="https://imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/9495519-letterbox-images-that-need-to-fit">#9495519 - letterbox images that need to fit</a></p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=letterbox&amp;bg=black"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=letterbox&amp;bg=black"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=letterbox&amp;bg=black" alt=""/></a>
                </section>
                <section id="crop" class="goto">
                    <h1 id="crop-position">Crop position <code>&amp;a=</code></h1>
                    <p>You can also set where the image is cropped by adding a crop position. Only works when <code>t=square</code>. Accepts <code>top</code>, <code>left</code>, <code>center</code>, <code>right</code> or <code>bottom</code>. Default is <code>center</code>. For more information, please see the suggestion on our UserVoice forum: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/2570350-aligning">#2570350 - Aligning</a>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=top"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=top"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=top" alt=""/></a>
                    <h3 id="crop-focal-point">Crop Focal Point <span class="new">New!</span></h3>
                    <p>In addition to the crop position, you can be more specific about the exact crop position using a focal point. Only works when <code>t=square</code>. This is defined using two offset percentages: <code>crop-x%-y%</code>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=crop-25-45"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=crop-25-45"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;a=crop-25-45" alt=""/></a>
                    <h3 id="crop-crop">Manual crop <code>&amp;crop=</code></h3>
                    <p>Crops the image to specific dimensions prior to any other resize operations. Required format: <code>width,height,x,y</code>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=300,300,680,500"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=300,300,680,500"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=300,300,680,500" alt=""/></a>
                </section>
                <section id="shape" class="goto">
                    <h1>Shape</h1>
                    <h3 id="shape-shape">Shape <code>&amp;shape=</code></h3>
                    <p>Crops the image to a specific shape. More info: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/3910149-add-circle-effect-to-photos">#3910149 - Add circle effect to photos</a>.</p>
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
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;h=300&amp;t=square&amp;shape=circle" alt=""/></a>
                </section>
                <section id="adjustments" class="goto">
                    <h1>Adjustments <span class="new">New!</span></h1>
                    <h3 id="brightness-bri">Brightness <code>bri</code></h3>
                    <p>Adjusts the image brightness. Use values between <code>-100</code> and <code>+100</code>, where <code>0</code> represents no change.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;bri=-25"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;bri=-25"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;bri=-25" alt=""/></a>
                    <!-- <h3 id="contrast-con">Contrast <code>con</code></h3>
                    <p>Adjusts the image contrast. Use values between <code>-100</code> and <code>+100</code>, where <code>0</code> represents no change.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;con=25"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;con=25"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;con=25" alt=""/></a> -->
                    <h3 id="gamma-gam">Gamma <code>gam</code></h3>
                    <p>Adjusts the image gamma. Use values between <code>1</code> and <code>3</code>. The default value is <code>2.2</code>, a suitable approximation for sRGB images.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;gam=3"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;gam=3"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;gam=3" alt=""/></a>
                    <h3 id="sharpen-sharp">Sharpen <code>sharp</code></h3>
                    <p>Sharpen the image. Required format: <code>f,j,r</code></p>
                    <h4 id="sharpen-arguments">Arguments:</h4>
                    <ul>
                        <li>Flat <code>f</code> -  Sharpening to apply to flat areas. (Default: 1.0)</li>
                        <li>Jagged <code>j</code> - Sharpening to apply to jagged areas. (Default: 2.0)</li>
                        <li>Radius <code>r</code> -  Sharpening mask to apply in pixels, but comes at a performance cost. (optional)</li>
                    </ul>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;sharp=5,5,3"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;sharp=5,5,3"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;sharp=5,5,3" alt=""/></a>
                    <h3 id="trim-trim">Trim <code>trim</code></h3>
                    <p>Trim "boring" pixels from all edges that contain values within a percentage similarity of the top-left pixel. Trimming occurs before any resize operation. Use values between <code>0</code> and <code>100</code> to define a percentaged tolerance level to trim away similar color values. You also can specify just &trim, which defaults to a percentaged tolerance level of 10.</p><p>More info: <a href="https://imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/3083264-able-to-remove-black-white-whitespace">#3083264 - Able to remove black/white whitespace</a></p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=300&amp;trim=10"&gt;</code></pre>
                    <a class="trimedges" href="$url/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=300&amp;trim=10"><img src="$url/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=300&amp;trim=10" alt=""/></a>
                    <h3 id="background-bg">Background <code>bg</code></h3>
                    <p>Sets the background color of the image. Supports a variety of color formats. In addition to the 140 color names supported by all modern browsers (listed <a href="$url/colors.html">here</a>), it also accepts hexadecimal RGB and RBG alpha formats.</p>
                    <h4 id="hexadecimal">Hexadecimal</h4>
                    <ul>
                        <li>3 digit RGB: <code>CCC</code></li>
                        <li>4 digit ARGB (alpha): <code>5CCC</code></li>
                        <li>6 digit RGB: <code>CCCCCC</code></li>
                        <li>8 digit ARGB (alpha): <code>55CCCCCC</code></li>
                    </ul>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=400&amp;bg=black"&gt;</code></pre>
                    <a href="$url/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=400&amp;bg=black"><img src="$url/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png&amp;w=400&amp;bg=black" alt=""/></a>
                </section>
                <section id="effects" class="goto">
                    <h1>Effects <span class="new">New!</span></h1>
                    <h3 id="blur-blur">Blur <code>blur</code></h3>
                    <p>Adds a blur effect to the image. Use values between <code>0</code> and <code>100</code>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;blur=5"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;blur=5"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;blur=5" alt=""/></a>
                    <h3 id="filter-filt">Filter <code>filt</code></h3>
                    <p>Applies a filter effect to the image. Accepts <code>greyscale</code> or <code>sepia</code>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;filt=greyscale"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;filt=greyscale"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;filt=greyscale" alt=""/></a>
                </section>
                <section id="encoding" class="goto">
                    <h1>Encoding</h1>
                    <h3 id="quality-q">Quality <code>&amp;q=</code></h3>
                    <p>Defines the quality of the image. Use values between <code>0</code> and <code>100</code>. Defaults to <code>85</code>. Only relevant if the format is set to <code>jpg</code>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;q=20"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;q=20"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;q=20" alt=""/></a>
                    <h3 id="output-output">Output <code>&amp;output=</code></h3>
                    <p>Encodes the image to a specific format. Accepts <code>jpg</code>, <code>png</code> or <code>webp</code>. If none is given, it will honor the origin image format.</p><p>More info: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/5097964-format-conversion">#5097964 - Format conversion</a>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;output=webp"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;output=webp"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;output=webp" alt=""/></a>
                    <h3 id="interlace-progressive-il">Interlace / progressive <code>&amp;il</code></h3>
                    <p>Adds interlacing to PNG. JPEG's become progressive.</p><p>More info: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/3998911-add-parameter-to-use-progressive-jpegs">#3998911 - Add parameter to use progressive JPEGs</a>.</p>
                    <pre><code class="language-html">&lt;img src="//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;il"&gt;</code></pre>
                    <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;il"><img src="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;w=300&amp;il" alt=""/></a>
                    <h3 id="base64-encoding">Base64 (data URL) <code>&amp;encoding=base64</code></h3>
                    <p>Encodes the image to be used directly in the src= of the <code>&lt;img&gt;</code>-tag. <a href="$url/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=100,100,680,500&amp;encoding=base64">Use this link to see the output result</a>.</p><p>More info: <a href="//imagesweserv.uservoice.com/forums/144259-images-weserv-nl-general/suggestions/4522336-return-image-base64-encoded">#4522336 - Return image base64 encoded</a>.</p>
                    <pre><code>//images.weserv.nl/?url=rbx.weserv.nl/lichtenstein.jpg&amp;crop=100,100,680,500&amp;encoding=base64</code></pre>
                </section>
            </div>
        </div>
    </section>
    <!-- UserVoice JavaScript -->
    <script type="text/javascript">
        (function() {
            var uv = document.createElement('script');
            uv.type = 'text/javascript';
            uv.async = true;
            uv.src = '//widget.uservoice.com/PLImJMGVdhdO2160d8dog.js';
            var s = document.getElementsByTagName('script')[0];
            s.parentNode.insertBefore(uv, s)
        })();
        UserVoice = window.UserVoice || [];
        UserVoice.push(['showTab', 'classic_widget', {
            mode: 'full',
            primary_color: '#292929',
            link_color: '#a72376',
            default_mode: 'feedback',
            forum_id: 144259,
            tab_label: 'Feedback',
            tab_color: '#a72376',
            tab_position: 'top-right',
            tab_inverted: false
        }]);
    </script>
</body>
</html>
HTML;
    echo $html;
}
?>