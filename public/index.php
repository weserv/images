<?php
/**
 * @author Andries Louw Wolthuizen
 * @author Kleis Auke Wolthuizen
 * @site images.weserv.nl
 * @copyright 2015
 **/

error_reporting(E_ALL);
set_time_limit(180);
ini_set('display_errors', 0);

require '../vendor/autoload.php';

use League\Uri\Schemes\Http as HttpUri;

if (!empty($_GET['url'])) {
    //Special rules
    if (substr($_GET['url'], 0, 1) == '/') {
        header('X-Notice: Malformed start of URL, autofix');
        //trigger_error('URL failed, autofix. URL: '.$_GET['url'],E_USER_NOTICE);
        if (substr($_GET['url'], 0, 2) == '/.') {
            $_GET['url'] = substr($_GET['url'], 2);
        } elseif (substr($_GET['url'], 0, 2) == '//') {
            $_GET['url'] = substr($_GET['url'], 2);
        } else {
            $_GET['url'] = substr($_GET['url'], 1);
        }
    } elseif (substr($_GET['url'], 0, 25) == 'www.mallublog.vt.vc/goto/') {
        header('X-Notice: Known redirect host, autofix');
        //trigger_error('URL redirects, autofix. URL: '.$_GET['url'],E_USER_NOTICE);
        $_GET['url'] = substr($_GET['url'], 25);
    }

    try {
        if (substr($_GET['url'], 0, 4) == 'ssl:') {
            $_GET['url'] = substr($_GET['url'], 4);
            $uri = HttpUri::createFromString('https://' . $_GET['url']);
        } else {
            if (substr($_GET['url'], 0, 5) != 'http:' && substr($_GET['url'], 0, 6) != 'https:') {
                $uri = HttpUri::createFromString('http://' . $_GET['url']);
            } else {
                throw new RuntimeException('Invalid URL');
            }
        }
    } catch (RuntimeException $e) {
        header('HTTP/1.0 404 Not Found');
        header('Content-type: text/plain');
        echo 'Error 404: Server could not parse the ?url= that you were looking for, because it isn\'t a valid url.';
        trigger_error('URL failed, unable to parse. URL: ' . $_GET['url'], E_USER_WARNING);
        die;
    }

    $allowed = [
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    $extension = array_key_exists($uri->path->getExtension(), $allowed) ? $uri->path->getExtension() : 'jpg';

    $tmpFileName = tempnam('/dev/shm', 'imo_');
    /*$newFileName = substr($tmpFileName, 0, -3) . $extension;
    rename($tmpFileName, $newFileName);
    $tmpFileName = $newFileName;*/

    // Create an image manager instance with favored driver (gd by default)
    $imageManager = new Intervention\Image\ImageManager([
        'driver' => 'imagick',
    ]);

    // Create an PHP HTTP client
    $client = new AndriesLouw\imagesweserv\Client($tmpFileName, [
        // User agent for this client
        'user_agent' => 'Mozilla/5.0 (compatible; ImageFetcher/6.0; +http://images.weserv.nl/)',
        // Float describing the number of seconds to wait while trying to connect to a server. Use 0 to wait indefinitely.
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

    // Set manipulators
    $manipulators = [
        new AndriesLouw\imagesweserv\Manipulators\Orientation(),
        new AndriesLouw\imagesweserv\Manipulators\Crop(),
        new AndriesLouw\imagesweserv\Manipulators\Size(71000000),
        new AndriesLouw\imagesweserv\Manipulators\Shape,
        new AndriesLouw\imagesweserv\Manipulators\Trim(),
        new AndriesLouw\imagesweserv\Manipulators\Brightness(),
        new AndriesLouw\imagesweserv\Manipulators\Contrast(),
        new AndriesLouw\imagesweserv\Manipulators\Gamma(),
        new AndriesLouw\imagesweserv\Manipulators\Sharpen(),
        new AndriesLouw\imagesweserv\Manipulators\Filter(),
        new AndriesLouw\imagesweserv\Manipulators\Blur(),
        new AndriesLouw\imagesweserv\Manipulators\Pixelate(),
        new AndriesLouw\imagesweserv\Manipulators\Background(),
        new AndriesLouw\imagesweserv\Manipulators\Border(),
        new AndriesLouw\imagesweserv\Manipulators\Encode(),
    ];

    // Set API
    $api = new AndriesLouw\imagesweserv\Api\Api($imageManager, $client, $manipulators);

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
         * @var string Manipulated image binary data.
         */
        $image = $server->outputImage($uri->__toString(), $_GET);
    } catch (Intervention\Image\Exception\NotReadableException $e) { // This error should not happen, it only happens if file_get_contents on the temp file failed.
        // We are not doing trigger_error here because that is already handled in Api.php
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
        header('Content-type: text/plain');
        echo 'Error 500: ' . $e->getMessage();
        die;
    } catch (\Intervention\Image\Exception\RuntimeException $e) {
        // We are not doing trigger_error here because that is already handled in Api.php
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
        header('Content-type: text/plain');
        echo 'Error 500: ' . $e->getMessage();
        die;
    } catch (\ImagickException $e) {
        // We are not doing trigger_error here because that is already handled in Api.php
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
        header('Content-type: text/plain');
        echo 'Error 500: ' . $e->getMessage();
        die;
    } catch (AndriesLouw\imagesweserv\Exception\ImageTooLargeException $e) {
        // We are not doing trigger_error here because that is already handled in Api.php
        header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
        header('Content-type: text/plain');
        echo $e->getMessage();
        die;
    } catch (GuzzleHttp\Exception\RequestException $e) {
        // We are not doing trigger_error here because that is already handled in Client.php
        $previousException = $e->getPrevious();

        // Check if there is a previous exception
        if ($previousException != null) {
            if ($previousException instanceof AndriesLouw\imagesweserv\Exception\ImageNotValidException) {
                header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
                header('Content-type: text/plain');
                echo $previousException->getMessage();
            } else {
                if ($previousException instanceof AndriesLouw\imagesweserv\Exception\ImageTooBigException) {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
                    header('Content-type: text/plain');
                    echo $previousException->getMessage();
                } else {
                    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
                    header('Content-type: text/plain');
                    echo 'Error 500: Unknown exception: ' . $previousException->getMessage();
                }
            }
        } else {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            header('Content-type: text/plain');
            echo 'Error 404: Server could not parse the ?url= that you were looking for. Message: ' . $e->getMessage();
        }
        die;
    }

    // Still here? All checks are valid so echo te response
    header('Expires: ' . date_create('+31 days')->format('D, d M Y H:i:s') . ' GMT'); //31 days
    header('Cache-Control: max-age=2678400'); //31 days

    if (array_key_exists('encoding', $_GET) && $_GET['encoding'] == 'base64') {
        //$base64 = $image->encode('data-url');
        // Comment or delete below if temp file mime type is fixed
        $userMimeType = array_key_exists('output', $_GET) ? $_GET['output'] : null;
        $base64 = sprintf('data:%s;base64,%s', $server->getCurrentMimeType($userMimeType, $extension, $allowed),
            base64_encode($image));

        header('Content-type: text/plain');
        header('Content-Length: ' . strlen($base64));

        echo $base64;
    } else {
        $userMimeType = array_key_exists('output', $_GET) ? $_GET['output'] : null;
        header('Content-type: ' . $server->getCurrentMimeType($userMimeType, $extension, $allowed));
        echo $image;
    }

    unlink($tmpFileName);
    exit;
} else {
    /*isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';*/
    $protocol = '//';
    $baseURL = 'images.weserv.nl';
    $fullURL = $protocol . $baseURL;

    $githubURL = 'https://github.com/andrieslouw/imagesweserv';
    $userVoiceURL = '//imagesweserv.uservoice.com';

    $description = 'Image cache &amp; resize proxy';
    $title = 'Images.weserv.nl - ' . $description;
    $name = 'Images.<b>weserv</b>.nl';

    $count = '450.000.000 images, 16 TB of traffic - each month';
    $location = 'RBX, FR';

    $sampleImage = $fullURL . '/?url=ssl:upload.wikimedia.org/wikipedia/commons/e/e8/Lichtenstein.jpg';
    $sampleImageName = $fullURL . '/?url=&#133;Lichtenstein.jpg';

    $sampleTransparentImage = $fullURL . '/?url=ssl:upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png';
    $sampleTransparentImageName = $fullURL . '/?url=&#133;transparency_demonstration.png';

    $apiVersion = 'v2';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>$title</title>
    <meta name="description" content="$description" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link href="css-compiled/nucleus.css" type="text/css" rel="stylesheet" />
    <link href="css-compiled/theme.css" type="text/css" rel="stylesheet" />
    <link href="css/font-awesome.min.css" type="text/css" rel="stylesheet" />
    <link href="css/featherlight.min.css" type="text/css" rel="stylesheet" />
    <!--[if lte IE 9]>
    <link href="css/nucleus-ie9.css" type="text/css" rel="stylesheet" />
    <link href="css/pure-0.5.0/grids-min.cs" type="text/css" rel="stylesheet" />
    <script src="js/html5shiv-printshiv.min.js" type="text/javascript"></script>
    <![endif]-->
    <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js" type="text/javascript"></script>
    <script src="js/modernizr.custom.71422.js" type="text/javascript"></script>
    <script src="js/featherlight.min.js" type="text/javascript"></script>
    <script src="js/clipboard.min.js" type="text/javascript"></script>
    <script src="js/jquery.scrollbar.min.js" type="text/javascript"></script>
    <script src="js/bootstrap.min.js" type="text/javascript"></script>
    <script src="js/imagesweserv.js" type="text/javascript"></script>
</head>
<body class="searchbox-hidden" data-spy="scroll" data-target=".scrollspy">
    <nav id="sidebar">
        <div id="header-wrapper">
            <div id="header">
                <a id="logo" href="$fullURL/">
                    <div id="weserv-logo">$name</div>
                    <span>$description</span>
                </a>
                <div class="searchbox">
                    <label for="search-by"><i class="fa fa-search"></i></label>
                    <input id="search-by" type="text" placeholder="Search Documentation" data-search-input />
                    <span data-search-clear><i class="fa fa-close"></i></span>
                </div>
            </div>
        </div>
        <div class="scrollbar-inner">
            <div class="highlightable scrollspy">
                <ul id="nav" class="nav topics" data-spy="affix">
                    <li class="dd-item"><a href="#introduction"><span><b>1. </b>Introduction</span></a></li>
                    <li class="dd-item"><a href="#image-api"><span><b>2. </b>Image API $apiVersion</span></a>
                        <ul class="nav">
                            <li class="dd-item"><a href="#deprecated"><span>Deprecated</span></a></li>
                            <li class="dd-item"><a href="#quick-reference"><span>Quick reference</span></a></li>
                            <li class="dd-item"><a href="#relative-dimensions"><span>Relative dimensions</span></a></li>
                            <li class="dd-item"><a href="#colors"><span>Colors</span></a></li>
                            <li class="dd-item"><a href="#orientation"><span>Orientation</span></a></li>
                            <li class="dd-item"><a href="#crop"><span>Crop</span></a></li>
                            <li class="dd-item"><a href="#size"><span>Size</span></a></li>
                            <li class="dd-item"><a href="#shape"><span>Shape</span></a></li>
                            <li class="dd-item"><a href="#pixel-density"><span>Pixel Density</span></a></li>
                            <li class="dd-item"><a href="#adjustments"><span>Adjustments</span></a></li>
                            <li class="dd-item"><a href="#effects"><span>Effects</span></a></li>
                            <li class="dd-item"><a href="#background"><span>Background</span></a></li>
                            <li class="dd-item"><a href="#border"><span>Border</span></a></li>
                            <li class="dd-item"><a href="#encode"><span>Encode</span></a></li>
                        </ul>
                    </li>
                </ul>
                <hr />
                <a class="padding github-link" href="$githubURL"><i class="fa fa-github-square"></i> Get the source code</a>
                <br/>
                <section id="footer">
                    <p>Design inspired from <a href="//getgrav.org">Grav</a></p>
                </section>
            </div>
        </div>
    </nav>
    <section id="body">
        <div class="padding highlightable">
            <a href="#" id="sidebar-toggle" data-sidebar-toggle><i class="fa fa-2x fa-bars"></i></a>
            <div id="body-inner">
                <section id="introduction" class="goto">
                    <h2>Introduction</h2>
                    <p>$name is an image <b>cache</b> &amp; <b>resize</b> proxy. Our servers resize your image, cache it worldwide, and display it.</p>
                    <ul>
                        <li>We don't support animated images (yet).</li>
                        <li>We do support GIF, JPEG, PNG, BMP, XBM, WebP and other filetypes!</li>
                        <li>We also support transparent images.</li>
                        <li>Full IPv6 support, <a href="http://ipv6-test.com/validate.php?url=$baseURL" rel="nofollow">serving dual stack</a>, and supporting <a href="//$baseURL/?url=ipv6.google.com/logos/logo.gif">IPv6-only origin hosts</a>.</li>
                        <li>SSL support, you can use <a href="https://$baseURL"><b>https</b>://$baseURL/</a>.
                            <br /><small class="sslnote">This can be very useful for embedding HTTP images on HTTPS websites. HTTPS origin hosts can be used by <a href="$userVoiceURL/forums/144259-images-weserv-nl-general/suggestions/2693328-add-support-to-fetch-images-over-https">prefixing the hostname with ssl:</a></small></li>
                    </ul>
                    <div class="notices info">
                        <p>We're part of the <a href="https://www.cloudflare.com/">CloudFlare</a> community. Images are being cached and delivered straight from <a href="https://www.cloudflare.com/network-map">71 global datacenters</a>. This ensures the fastest load times and best performance.</p>
                    </div>
                </section>
                <section id="image-api" class="goto">
                    <h2>Image API $apiVersion</h2>
                    <p>Requesting an image:</p>
                    <ul>
                        <li><code>?url=</code> (URL encoded) link to your image, without http://</li>
                    </ul>
                </section>
                <section id="deprecated" class="goto">
                    <h3>Deprecated</h3>
                    <div class="notices warning">
                        <p>At the end of December 2015 we introduced Version 2 of the $name API. We also kept Version 1 of the API in place so as not to break anyone's apps.</br></br>Please update all of your code to point to Version 2 of the API. All of the Version 2 improvements resulted in new URLs for the API so please consult the documentation to learn how to access them.</p>
                    </div>
                    <h4 id="deprecated-values">Deprecated URL-parameter values</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Function</th>
                                <th>Value</th>
                                <th>Use instead</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>t</code></td>
                                <td><code style="color:red;">=fit</code></td>
                                <td><code style="color:green;">=contain</code></td>
                                <td><a href="#fit-fit">info</a></td>
                            </tr>
                            <tr>
                                <td><code>t</code></td>
                                <td><code style="color:red;">=fitup</code></td>
                                <td><code style="color:green;">=contain</code></td>
                                <td><a href="#fit-fit">info</a></td>
                            </tr>
                            <tr>
                                <td><code>t</code></td>
                                <td><code style="color:red;">=square</code></td>
                                <td><code style="color:green;">=crop</code></td>
                                <td><a href="#fit-fit">info</a></td>
                            </tr>
                            <tr>
                                <td><code>t</code></td>
                                <td><code style="color:red;">=squaredown</code></td>
                                <td><code style="color:green;">=crop</code></td>
                                <td><a href="#fit-fit">info</a></td>
                            </tr>
                            <tr>
                                <td><code>t</code></td>
                                <td><code style="color:red;">=absolute</code></td>
                                <td><code style="color:green;">=stretch</code></td>
                                <td><a href="#fit-fit">info</a></td>
                            </tr>
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
                        </tbody>
                    </table>
                    <h4 id="deprecated-functions">Deprecated URL-parameters</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Function</th>
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
                    <h3>Quick reference</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Function</th>
                                <th>Description</th>
                                <th>&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Orientation</td>
                                <td><code>or</code></td>
                                <td>Rotates the image.</td>
                                <td><a href="#orientation-or">info</a></td>
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
                                <td>Fit</td>
                                <td><code>t</code></td>
                                <td>Sets how the image is fitted to its target dimensions.</td>
                                <td><a href="#fit-fit">info</a></td>
                            </tr>
                            <tr>
                                <td>Shape</td>
                                <td><code>shape</code></td>
                                <td>Crops the image to a specific shape.</td>
                                <td><a href="#shape-shape">info</a></td>
                            </tr>
                            <tr>
                                <td>Device pixel ratio</td>
                                <td><code>dpr</code></td>
                                <td>Multiples the overall image size.</td>
                                <td><a href="#device-pixel-ratio-dpr">info</a></td>
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
                                <td>Blur</td>
                                <td><code>blur</code></td>
                                <td>Adds a blur effect to the image.</td>
                                <td><a href="#blur-blur">info</a></td>
                            </tr>
                            <tr>
                                <td>Pixelate</td>
                                <td><code>pixel</code></td>
                                <td>Applies a pixelation effect to the image.</td>
                                <td><a href="#pixelate-pixel">info</a></td>
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
                                <td>Border</td>
                                <td><code>border</code></td>
                                <td>Add a border to the image.</td>
                                <td><a href="#border-border">info</a></td>
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
                                <td>Encodes the image to be used directly in the src= of the <img>-tag.</td>
                                <td><a href="#base64-encoding">info</a></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <section id="relative-dimensions" class="goto">
                    <h3>Relative dimensions</h3>
                    <p>Relative dimensions allow you to specify a width or height value as a percentage of the main image. This is helpful for features like borders.</p>
                    <p>To use a relative dimension, simply provide a percentage as a number (between <code>0</code> and <code>100</code>), followed by a <code>w</code> (width) or <code>h</code> (height). For example, <code>5w</code> represents 5% of the width of the main image.</p>
                     <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=300&amp;border=2w,5000,overlay"&gt;</code></pre>
                </section>
                <section id="colors" class="goto">
                    <h3>Colors</h3>
                    <p>$name supports a variety of color formats. In addition to the 140 color names supported by all modern browsers (listed below), $name accepts hexadecimal RGB and RBG alpha formats.</p>
                    <h4 id="hexadecimal">Hexadecimal</h4>
                    <ul>
                        <li>3 digit RGB: <code>CCC</code></li>
                        <li>4 digit ARGB (alpha): <code>5CCC</code></li>
                        <li>6 digit RGB: <code>CCCCCC</code></li>
                        <li>8 digit ARGB (alpha): <code>55CCCCCC</code></li>
                    </ul>
                    <h4 id="color-names">Color names</h4>
                    <button type="button" class="button-secondary" id="show-hide-color" data-toggle="collapse" data-target="#color-table-collapsible"><i class="fa fa-arrow-down"></i> Show color names</button>
                    <div id="color-table-collapsible" class="collapse" data-caller="#show-hide-color">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Color</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>aliceblue</td>
                                    <td style="background:#F0F8FF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>antiquewhite</td>
                                    <td style="background:#FAEBD7;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>aqua</td>
                                    <td style="background:#00FFFF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>aquamarine</td>
                                    <td style="background:#7FFFD4;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>azure</td>
                                    <td style="background:#F0FFFF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>beige</td>
                                    <td style="background:#F5F5DC;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>bisque</td>
                                    <td style="background:#FFE4C4;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>black</td>
                                    <td style="background:#000000;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>blanchedalmond</td>
                                    <td style="background:#FFEBCD;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>blue</td>
                                    <td style="background:#0000FF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>blueviolet</td>
                                    <td style="background:#8A2BE2;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>brown</td>
                                    <td style="background:#A52A2A;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>burlywood</td>
                                    <td style="background:#DEB887;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>cadetblue</td>
                                    <td style="background:#5F9EA0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>chartreuse</td>
                                    <td style="background:#7FFF00;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>chocolate</td>
                                    <td style="background:#D2691E;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>coral</td>
                                    <td style="background:#FF7F50;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>cornflowerblue</td>
                                    <td style="background:#6495ED;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>cornsilk</td>
                                    <td style="background:#FFF8DC;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>crimson</td>
                                    <td style="background:#DC143C;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>cyan</td>
                                    <td style="background:#00FFFF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkblue</td>
                                    <td style="background:#00008B;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkcyan</td>
                                    <td style="background:#008B8B;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkgoldenrod</td>
                                    <td style="background:#B8860B;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkgray</td>
                                    <td style="background:#A9A9A9;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkgreen</td>
                                    <td style="background:#006400;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkkhaki</td>
                                    <td style="background:#BDB76B;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkmagenta</td>
                                    <td style="background:#8B008B;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkolivegreen</td>
                                    <td style="background:#556B2F;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkorange</td>
                                    <td style="background:#FF8C00;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkorchid</td>
                                    <td style="background:#9932CC;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkred</td>
                                    <td style="background:#8B0000;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darksalmon</td>
                                    <td style="background:#E9967A;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkseagreen</td>
                                    <td style="background:#8FBC8F;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkslateblue</td>
                                    <td style="background:#483D8B;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkslategray</td>
                                    <td style="background:#2F4F4F;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkturquoise</td>
                                    <td style="background:#00CED1;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>darkviolet</td>
                                    <td style="background:#9400D3;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>deeppink</td>
                                    <td style="background:#FF1493;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>deepskyblue</td>
                                    <td style="background:#00BFFF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>dimgray</td>
                                    <td style="background:#696969;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>dodgerblue</td>
                                    <td style="background:#1E90FF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>firebrick</td>
                                    <td style="background:#B22222;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>floralwhite</td>
                                    <td style="background:#FFFAF0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>forestgreen</td>
                                    <td style="background:#228B22;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>fuchsia</td>
                                    <td style="background:#FF00FF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>gainsboro</td>
                                    <td style="background:#DCDCDC;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>ghostwhite</td>
                                    <td style="background:#F8F8FF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>gold</td>
                                    <td style="background:#FFD700;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>goldenrod</td>
                                    <td style="background:#DAA520;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>gray</td>
                                    <td style="background:#808080;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>green</td>
                                    <td style="background:#008000;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>greenyellow</td>
                                    <td style="background:#ADFF2F;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>honeydew</td>
                                    <td style="background:#F0FFF0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>hotpink</td>
                                    <td style="background:#FF69B4;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>indianred</td>
                                    <td style="background:#CD5C5C;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>indigo</td>
                                    <td style="background:#4B0082;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>ivory</td>
                                    <td style="background:#FFFFF0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>khaki</td>
                                    <td style="background:#F0E68C;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lavender</td>
                                    <td style="background:#E6E6FA;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lavenderblush</td>
                                    <td style="background:#FFF0F5;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lawngreen</td>
                                    <td style="background:#7CFC00;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lemonchiffon</td>
                                    <td style="background:#FFFACD;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightblue</td>
                                    <td style="background:#ADD8E6;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightcoral</td>
                                    <td style="background:#F08080;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightcyan</td>
                                    <td style="background:#E0FFFF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightgoldenrodyellow</td>
                                    <td style="background:#FAFAD2;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightgray</td>
                                    <td style="background:#D3D3D3;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightgreen</td>
                                    <td style="background:#90EE90;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightpink</td>
                                    <td style="background:#FFB6C1;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightsalmon</td>
                                    <td style="background:#FFA07A;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightseagreen</td>
                                    <td style="background:#20B2AA;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightskyblue</td>
                                    <td style="background:#87CEFA;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightslategray</td>
                                    <td style="background:#778899;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightsteelblue</td>
                                    <td style="background:#B0C4DE;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lightyellow</td>
                                    <td style="background:#FFFFE0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>lime</td>
                                    <td style="background:#00FF00;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>limegreen</td>
                                    <td style="background:#32CD32;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>linen</td>
                                    <td style="background:#FAF0E6;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>magenta</td>
                                    <td style="background:#FF00FF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>maroon</td>
                                    <td style="background:#800000;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumaquamarine</td>
                                    <td style="background:#66CDAA;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumblue</td>
                                    <td style="background:#0000CD;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumorchid</td>
                                    <td style="background:#BA55D3;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumpurple</td>
                                    <td style="background:#9370DB;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumseagreen</td>
                                    <td style="background:#3CB371;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumslateblue</td>
                                    <td style="background:#7B68EE;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumspringgreen</td>
                                    <td style="background:#00FA9A;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumturquoise</td>
                                    <td style="background:#48D1CC;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mediumvioletred</td>
                                    <td style="background:#C71585;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>midnightblue</td>
                                    <td style="background:#191970;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mintcream</td>
                                    <td style="background:#F5FFFA;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>mistyrose</td>
                                    <td style="background:#FFE4E1;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>moccasin</td>
                                    <td style="background:#FFE4B5;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>navajowhite</td>
                                    <td style="background:#FFDEAD;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>navy</td>
                                    <td style="background:#000080;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>oldlace</td>
                                    <td style="background:#FDF5E6;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>olive</td>
                                    <td style="background:#808000;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>olivedrab</td>
                                    <td style="background:#6B8E23;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>orange</td>
                                    <td style="background:#FFA500;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>orangered</td>
                                    <td style="background:#FF4500;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>orchid</td>
                                    <td style="background:#DA70D6;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>palegoldenrod</td>
                                    <td style="background:#EEE8AA;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>palegreen</td>
                                    <td style="background:#98FB98;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>paleturquoise</td>
                                    <td style="background:#AFEEEE;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>palevioletred</td>
                                    <td style="background:#DB7093;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>papayawhip</td>
                                    <td style="background:#FFEFD5;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>peachpuff</td>
                                    <td style="background:#FFDAB9;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>peru</td>
                                    <td style="background:#CD853F;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>pink</td>
                                    <td style="background:#FFC0CB;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>plum</td>
                                    <td style="background:#DDA0DD;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>powderblue</td>
                                    <td style="background:#B0E0E6;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>purple</td>
                                    <td style="background:#800080;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>rebeccapurple</td>
                                    <td style="background:#663399;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>red</td>
                                    <td style="background:#FF0000;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>rosybrown</td>
                                    <td style="background:#BC8F8F;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>royalblue</td>
                                    <td style="background:#4169E1;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>saddlebrown</td>
                                    <td style="background:#8B4513;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>salmon</td>
                                    <td style="background:#FA8072;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>sandybrown</td>
                                    <td style="background:#F4A460;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>seagreen</td>
                                    <td style="background:#2E8B57;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>seashell</td>
                                    <td style="background:#FFF5EE;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>sienna</td>
                                    <td style="background:#A0522D;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>silver</td>
                                    <td style="background:#C0C0C0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>skyblue</td>
                                    <td style="background:#87CEEB;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>slateblue</td>
                                    <td style="background:#6A5ACD;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>slategray</td>
                                    <td style="background:#708090;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>snow</td>
                                    <td style="background:#FFFAFA;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>springgreen</td>
                                    <td style="background:#00FF7F;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>steelblue</td>
                                    <td style="background:#4682B4;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>tan</td>
                                    <td style="background:#D2B48C;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>teal</td>
                                    <td style="background:#008080;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>thistle</td>
                                    <td style="background:#D8BFD8;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>tomato</td>
                                    <td style="background:#FF6347;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>turquoise</td>
                                    <td style="background:#40E0D0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>violet</td>
                                    <td style="background:#EE82EE;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>wheat</td>
                                    <td style="background:#F5DEB3;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>white</td>
                                    <td style="background:#FFFFFF;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>whitesmoke</td>
                                    <td style="background:#F5F5F5;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>yellow</td>
                                    <td style="background:#FFFF00;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td>yellowgreen</td>
                                    <td style="background:#9ACD32;">&nbsp;</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section id="orientation" class="goto">
                    <h3>Orientation</h3>
                    <h4 id="orientation-or">Orientation <code>or</code></h4>
                    <p>Rotates the image. Accepts <code>auto</code>, <code>0</code>, <code>90</code>, <code>180</code> or <code>270</code>. Default is <code>auto</code>. The <code>auto</code> option uses Exif data to automatically orient images correctly.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;h=500&amp;or=90"&gt;</code></pre>
                    <a href="$sampleImage&h=500&or=90"><img src="$sampleImage&h=500&or=90"/></a>
                </section>
                <section id="crop" class="goto">
                    <h3>Crop</h3>
                    <h4 id="fit-fitcrop">Fit <code>t=crop</code></h4>
                    <p>Resizes the image to fill the width and height boundaries and crops any excess image data. The resulting image will match the width and height constraints without distorting the image.</p>
                    <pre><code class="language-html">&lt;img src=$sampleImageName&amp;w=300&amp;h=300&amp;t=crop"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=300&amp;h=300&amp;t=crop"><img src="$sampleImage&amp;w=300&amp;h=300&amp;t=crop"/></a>
                    <h5 id="crop-position">Crop Position <code>a=center</code></h5>
                    <p>You can also set where the image is cropped by adding a crop position. Only works when <code>t=crop</code>. Accepts <code>top-left</code>, <code>top</code>, <code>top-right</code>, <code>left</code>, <code>center</code>, <code>right</code>, <code>bottom-left</code>, <code>bottom</code> or <code>bottom-right</code>. Default is <code>center</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=300&amp;h=300&amp;t=crop&amp;a=top-left"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=300&amp;h=300&amp;t=crop&amp;a=top-left"><img src="$sampleImage&amp;w=300&amp;h=300&amp;t=crop&amp;a=top-left"/></a>
                    <h5 id="crop-focal-point">Crop Focal Point</h5>
                    <p>In addition to the crop position, you can be more specific about the exact crop position using a focal point. Only works when <code>t=crop</code>. This is defined using two offset percentages: <code>crop-x%-y%</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=300&amp;h=300&amp;t=crop&amp;a=crop-25-45"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=300&amp;h=300&amp;t=crop&amp;a=crop-25-45"><img src="$sampleImage&amp;w=300&amp;h=300&amp;t=crop&amp;a=crop-25-45"/></a>
                    <h4 id="crop-crop">Crop <code>crop</code></h4>
                    <p>Crops the image to specific dimensions prior to any other resize operations. Required format: <code>width,height,x,y</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;crop=100,100,400,450"&gt;</code></pre>
                    <a href="$sampleImage&amp;crop=100,100,400,450"><img src="$sampleImage&amp;crop=100,100,400,450"/></a>
                </section>
                <section id="size" class="goto">
                    <h3>Size</h3>
                    <h4 id="width-w">Width <code>w</code></h4>
                    <p>Sets the width of the image, in pixels.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500"><img src="$sampleImage&amp;w=500" /></a>
                    <h4 id="height-h">Height <code>h</code></h4>
                    <p>Sets the height of the image, in pixels.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;h=333"&gt;</code></pre>
                    <a href="$sampleImage&amp;h=333"><img src="$sampleImage&amp;h=333" /></a>
                    <h4 id="fit-fit">Fit <code>t</code></h4>
                    <p>Sets how the image is fitted to its target dimensions.</p>
                    <h5 id="size-accepts">Accepts:</h5>
                    <ul>
                        <li><code>contain</code>: Default. Resizes the image to fit within the width and height boundaries without cropping, distorting or altering the aspect ratio.</li>
                        <li><code>max</code>: Resizes the image to fit within the width and height boundaries without cropping, distorting or altering the aspect ratio, and will also not increase the size of the image if it is smaller than the output size. </li>
                        <li><code>fill</code>: Resizes the image to fit within the width and height boundaries without cropping or distorting the image, and the remaining space is filled with the background color. The resulting image will match the constraining dimensions.</li>
                        <li><code>stretch</code>: Stretches the image to fit the constraining dimensions exactly. The resulting image will fill the dimensions, and will not maintain the aspect ratio of the input image.</li>
                        <li><code>crop</code>: Resizes the image to fill the width and height boundaries and crops any excess image data. The resulting image will match the width and height constraints without distorting the image. See the <a href="#crop">crop</a> page for more information.</li>
                    </ul>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=400&amp;h=300&amp;t=crop"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=400&amp;h=300&amp;t=crop"><img src="$sampleImage&amp;w=400&amp;h=300&amp;t=crop" /></a>
                </section>
                <section id="shape" class="goto">
                    <h3>Shape</h3>
                    <h4 id="shape-shape">Shape <code>shape</code></h4>
                    <p>Crops the image to a specific shape.</p>
                    <h5 id="shape-accepts">Accepts:</h5>
                    <ul>
                        <li><code>circle</code></li>
                        <li><code>ellipse</code></li>
                        <li><code>triangle-180</code>: Triangle tilted upside down</li>
                        <li><code>triangle</code></li>
                        <li><code>square</code></li>
                        <li><code>pentagon-180</code>: Pentagon tilted upside down</li>
                        <li><code>pentagon</code></li>
                        <li><code>star-3</code>: 3-point star</li>
                        <li><code>star-4</code>: 4-point star</li>
                        <li><code>star-5</code>: 5-point star</li>
                        <li><code>star</code> (same as <code>star-5</code>)</li>
                    </ul>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=250&amp;shape=star"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=250&amp;shape=star"><img src="$sampleImage&amp;w=250&amp;shape=star"/></a>
                </section>
                <section id="pixel-density" class="goto">
                    <h3>Pixel Density</h3>
                    <h4 id="device-pixel-ratio-dpr">Device pixel ratio <code>dpr</code></h4>
                    <p>The device pixel ratio is used to easily convert between CSS pixels and device pixels. This makes it possible to display images at the correct pixel density on a variety of devices such as Apple devices with Retina Displays and Android devices. You must specify either a width, a height, or both for this parameter to work. The default is 1. The maximum value that can be set for dpr is 8.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=250&amp;dpr=2"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=250&amp;dpr=2"><img src="$sampleImage&amp;w=250&amp;dpr=2"/></a>
                </section>
                <section id="adjustments" class="goto">
                    <h3>Adjustments</h3>
                    <h4 id="brightness-bri">Brightness <code>bri</code></h4>
                    <p>Adjusts the image brightness. Use values between <code>-100</code> and <code>+100</code>, where <code>0</code> represents no change.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;bri=-25"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;bri=-25"><img src="$sampleImage&amp;w=500&amp;bri=-25"/></a>
                    <h4 id="contrast-con">Contrast <code>con</code></h4>
                    <p>Adjusts the image contrast. Use values between <code>-100</code> and <code>+100</code>, where <code>0</code> represents no change.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;con=25"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;con=25"><img src="$sampleImage&amp;w=500&amp;con=25"/></a>
                    <h4 id="gamma-gam">Gamma <code>gam</code></h4>
                    <p>Adjusts the image gamma. Use values between <code>0.1</code> and <code>9.99</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;gam=1.5"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;gam=1.5"><img src="$sampleImage&amp;w=500&amp;gam=1.5"/></a>
                    <h4 id="sharpen-sharp">Sharpen <code>sharp</code></h4>
                    <p>Sharpen the image. Use values between <code>0</code> and <code>100</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;sharp=15"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;sharp=15"><img src="$sampleImage&amp;w=500&amp;sharp=15"/></a>
                    <h4 id="trim-trim">Trim <code>trim</code></h4>
                    <p>Trim away image space. Use values between <code>0</code> and <code>100</code> to define a percentaged tolerance level to trim away similar color values. You also can specify just &trim, which defaults to a percentaged tolerance level of 10.</p>
                    <pre><code class="language-html">&lt;img src="$sampleTransparentImageName&amp;w=500&amp;bg=black&amp;trim=10"&gt;</code></pre>
                    <a href="$sampleTransparentImage&amp;w=500&amp;bg=black&amp;trim=10"><img src="$sampleTransparentImage&amp;w=500&amp;bg=black&amp;trim=10"/></a>
                </section>
                <section id="effects" class="goto">
                    <h3>Effects</h3>
                    <h4 id="blur-blur">Blur <code>blur</code></h4>
                    <p>Adds a blur effect to the image. Use values between <code>0</code> and <code>100</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;blur=5"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;blur=5"><img src="$sampleImage&amp;w=500&amp;blur=5"/></a>
                    <h4 id="pixelate-pixel">Pixelate <code>pixel</code></h4>
                    <p>Applies a pixelation effect to the image. Use values between <code>0</code> and <code>1000</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;pixel=5"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;pixel=5"><img src="$sampleImage&amp;w=500&amp;pixel=5"/></a>
                    <h4 id="filter-filt">Filter <code>filt</code></h4>
                    <p>Applies a filter effect to the image. Accepts <code>greyscale</code> or <code>sepia</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;filt=sepia"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;filt=sepia"><img src="$sampleImage&amp;w=500&amp;filt=sepia"/></a>
                </section>
                <section id="background" class="goto">
                    <h3>Background</h3>
                    <h4 id="background-bg">Background <code>bg</code></h4>
                    <p>Sets the background color of the image. See <a href="#colors">colors</a> for more information on the available color formats.</p>
                    <pre><code class="language-html">&lt;img src="$sampleTransparentImageName&amp;w=400&amp;bg=black"&gt;</code></pre>
                    <a href="$sampleTransparentImage&amp;w=400&amp;bg=black"><img src="$sampleTransparentImage&amp;w=400&amp;bg=black"/></a>
                </section>
                <section id="border" class="goto">
                    <h3>Border</h3>
                    <h4 id="border-border">Border <code>border</code></h4>
                    <p>Add a border to the image. Required format: <code>width,color,method</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;border=10,5000,overlay"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;border=10,5000,overlay"><img src="$sampleImage&amp;w=500&amp;border=10,5000,overlay"/></a>
                    <h5 id="width">Width</h5>
                    <p>Sets the border width in pixels, or using <a href="#relative-dimensions">relative dimensions</a>.</p>
                    <h5 id="color">Color</h5>
                    <p>Sets the border color. See <a href="#colors">colors</a> for more information on the available color formats.</p>
                    <h5 id="method">Method</h5>
                    <p>Sets how the border will be displayed. Available options:</p>
                    <ul>
                        <li><code>overlay</code>: Place border on top of image (default).</li>
                        <li><code>shrink</code>: Shrink image within border (canvas does not change).</li>
                        <li><code>expand</code>: Expands canvas to accommodate border.</li>
                    </ul>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;border=10,FFCC33,expand"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;border=10,FFCC33,expand"><img src="$sampleImage&amp;w=500&amp;border=10,FFCC33,expand"/></a>
                </section>
                <section id="encode" class="goto">
                    <h3>Encode</h3>
                    <h4 id="quality-q">Quality <code>q</code></h4>
                    <p>Defines the quality of the image. Use values between <code>0</code> and <code>100</code>. Defaults to <code>85</code>. Only relevant if the format is set to <code>jpg</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;q=20"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;q=20"><img src="$sampleImage&amp;w=500&amp;q=20"/></a>
                    <h4 id="output-output">Output <code>output</code></h4>
                    <p>Encodes the image to a specific format. Accepts <code>jpg</code>, <code>png</code> or <code>gif</code>. Defaults to <code>jpg</code>.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;output=gif"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;output=gif"><img src="$sampleImage&amp;w=500&amp;output=gif"/></a>
                    <h4 id="interlace-progressive-il">Interlace / progressive <code>il</code></h4>
                    <p>Adds interlacing to GIF and PNG. JPEG's become progressive.</p>
                    <pre><code class="language-html">&lt;img src="$sampleImageName&amp;w=500&amp;il"&gt;</code></pre>
                    <a href="$sampleImage&amp;w=500&amp;il"><img src="$sampleImage&amp;w=500&amp;il"/></a>
                    <h4 id="base64-encoding">Base64 (data URL) <code>encoding=base64</code></h4>
                    <p>Encodes the image to be used directly in the src= of the <code>&lt;img&gt;</code>-tag.</p>
                    <pre><code>$sampleImageName&amp;crop=100,100,400,450&amp;encoding=base64</code></pre>
                    <a href="$sampleImage&amp;crop=100,100,400,450&amp;encoding=base64"><img src="data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAUDBAQEAwUEBAQFBQUGBwwIBwcHBw8LCwkMEQ8SEhEPERETFhwXExQaFRERGCEYGh0dHx8fExciJCIeJBweHx7/2wBDAQUFBQcGBw4ICA4eFBEUHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh7/wAARCABkAGQDASIAAhEBAxEB/8QAHAAAAQQDAQAAAAAAAAAAAAAABwAEBggCAwUB/8QANhAAAgEDAgQFAwMCBQUAAAAAAQIDAAQRBQYHEiExEyJBUWEUMnEII0JSoRUWM2JyFyWCkfD/xAAaAQABBQEAAAAAAAAAAAAAAAAFAAECAwQG/8QALhEAAQMDAgUDAQkAAAAAAAAAAAECAwQREiExBRMiQVEUIzMyFSQ0QlJhcaHh/9oADAMBAAIRAxEAPwC2gfAyBk+1LzAksoJPevEwvU9/evPubly2ayFpvjUAZHU+1bQhJAdOnxWEMb/yKj8U317Uo9NsmYyokjDEYY/c3sKZ8iRtyUZEVVshzN67rsNrWX1Ny7OzHCoB3P5oTazvrVJN72V1HO62MpRkT0APeo7res3255L+wuuaS58QzQpg9CO4xU34acOZNR0u0v8AXw8ax58OJTgup7c3tQS8tVJduiHQw0sNHHnPuQVrmSHdmsMY25XWYdsV4s8llta7SMMFupUB6eg7irIpoWiQscWFsGf7mKA5rObQ9Glg8GTT7Zox1C+GK0fZS/qKk4zGiWx8f0Vue6+l0C00q3z4k3702D1z6CpPoW+b3RLiy0i3f6nDHxcsepPpn4qa7n4XWNzcvqGjv9Pc8p5Yj1Qn0HxQa1XR9V27fyQ3sPJeSHCtjPL81glinp35LsEIZKasbZN/3LK6Rr2maxzJb3cbSxnDJnqDXQEZJORgCqv6Ze3u11+shuDNfzd1znlHvR52BuldX0yBL90ivWTm8MnqRRSkrEl0duBa7hj4OpuxIyrZ6dB7UqcNHzHOcUqIAq5z+ZQOvmPvW4fYOYggelNYhmR1PkwOuacxRAurNny+vvTISHAZUQtKMAdzQO4x6kmuP4ujXsjPZviSND2P9Qoj8UtfXQNuPIwBaX9tRnqfmhBw90SXXd0pLbuVtH87g9wP6W96HV0qufyG7qFeGU6Jed+yEu4PbRS5Zd0ahGwlcAojDrkdzRbdgi+QYAGB7V5FDHbwpDCPDjjXlAA7D3rVcByPDU5rbFCkLbIYqmpdUSZLsaEdmd+cBsd/mt0bsqjmJasPDAUe471vCMVXDeuOtPdSh1jZC5HQ1yN17asNetT48KG4Rf2pPVTXTjkVm8NSpYex61nH5H5ebNPikrcHiY98bs26Fadx6Pd7b1FzfRhph1QMMg/7vxXN25dX1lrMeuXd29vGhypB+8+wzR+4obeg1fbs1ytt4t5bpzxEevx+Kr1b2cl9eF9Yu2tooOmOXrn4WgM0C00llU62kqm1kK337/4WY21rtlrWjQahC5AcdQT1B9qVA7S99LoNoNP061zApLZY9ST60q3Nrm2BjuFOyWyKHcupyCe3fpT+1AaFGPr3rlBnDB16g96fX07W+kzzY6onMfmiSPREuBVbcCPGrdL3O4zozWyvaRjGSPMD7rU/4Obcj0bbUd0xZ5bkc+SOvKfShBY3d1ufecFlewLOJLgeZl5WCg9asxBCkFvHCg5UjUAAUOomc17pnBmvd6embA3uNtavIrOwluZCQEGPzUY2puMXmoPa3JCh/wDSJplxO1lYY2t1TmSMZbr3NCnbe6SdwJYzzhJn88Y9R8VoWRVfYEMbfRSyhiHOG/8AdcvdWprpmnFkI8VhhPj5p1oV+t9pcdyxAYDz/FCTi/uRo4p3WRgDlIh8DualNJg26EURVMNA3HLHqzyW1wZJFfEgL5/tRe0i+g1KyW5jwCRhlz2NUY2xvyfTd7iE4aCSTztnqatlsPVnDwqjD6eYZC/NQjc5i3UudH03J+DzKUPyD+KAfGDbyaLrz6kSwtLluZAqno3tR9YYORUX4paNDq+07gTRGRoB4igHrkU1bFzYV8oXcPqVp579lK2pq9ug5Y7KN1H8m7mlT03l7Axht7SFUQ46Q56/mlXPo9DtEfoWat1HiKXKk59BXJ4m3c1ls68eDPiFeVCort2ioXGR2GelQT9QUk6bTjjt5RG5mDffy5Aro6xVbAqocNTMznY3sQzgPbX19ut7q7UlLeMtlkwck4o8ancLaWUtw3ZFoW/px064t9Hvry5kEjSuEVg2ew6/3qd75nEWjFR/I4NNSt5dLc0cUflVK3wBze954qS3M8pEaKZH61WV9evJN+HWLd3EdvJlSPtAoy8Zb+SDb80cT/uXj8oAPYUH9Tsv8H0y3tuXMsqCWUfB7VCFuS2KWIjVyUuXsfcCXG0orm2ZnS7hyOnY4xQO426tyLc8spJBEcXX1PepxwC1BbrhsnOpR4WZVI7YHahTxXje+3DZ6cF6yvzEVF3S6zuxHG8lkBtoWmNNN9QxHiDzZqzHCLU55tGhSVzzwMOvxQIieFL+7VV8KOOTkQj0opcI7hxeiCOcSRNHnAonLE30yOItf1YlpbGX6iziuF6FkzjNbZVEltJGw5sqR1HeuXtdmOhwBR1AxXTglLAhxy1mat228lTks4rHuy1vbXcN5CZjGBIcKCB0/FKiHvXhveavuO6v4LuCKOQ5CsSSKVAfRy+DroeIRctuUmtgoWfKqsyD1/tQi/UjNHN9DatdLHhSxU560W7N16tjlFcXdmydH3VNBcag9xzRjACvijVZHJNFi05mikjhnR7tjncCLX6TYkC5DB2LBh61nxRmk5IYVLAAcxwcZqU6FY2+lWkWn2icsEKcqgelQvigea+AZvKI+iink6YUaQmfzJleALiXH9VqFhaMAQHzgdc0OOIIT/N16IHJaGNEAPYYHWi1ukLJuKwLeXrjoPWhTvKOKfd2rRghHz/qelKiTKYsd8Yff03wt/0zu5GVc87Yz6eWoPva3ih3/Zu7g/tsy83/AN8VPuAcZi4XTx8/iAO2CPXpUK3wkb79sA4DqIHyCP8Aa1V1LF5iiavuAxjhmxMZlDK8hbpRB4JxFdxSNz+URYAx2qE2cdwok8NudS3QN/Gp/wAGOWbcbD7XWPz/ADRupajaUyxO90tDtcf9hiy5B606mlEaZzzGufttAmmIhY59DTwqC2SuRQZirihY7RxqLyynnWPoaVZSSyByFAA/NKpXUVkNx5cdsn2rAlwQAcDGa9RgSqFutYHy82T3OPwKv2aQsO7bIwS3U98UOuI7zHU3fAHLH3xRDgXOMHtUJ3+I/wDEHL5JCYA96ol1bqO3cC255Cu49PHPytnOcUIN5zY3Vqiyx/tGc/b3/FF/euf8zWAKBQpwpx281CXdMJO5dVlLeIpmyFxSodKjTwaF1j1LGcBJI04UzqF8pZuTHeoHxDkEe/rPDL0gfPX4ap7wLiVeFzMTyHnahzxOyN9WzRyBmFuxII/5VXUOykExvuECgPMspSVlPN1Ge9EDgQc7kn8RDzeF5j70PEWN0JdvB5m+4USuA8LruCcu5kXwuhHrRqqX7vYzRImblLOaCijS43A5QM96dMylSV9K16aoOkw8vlBHXNYMzBR16D+9CkSzB11UzKIxyehNKsTIufMAT70qkI85QzqTSiYlWz1pUqn2EObQAFh/T2qB79djdtk9qVKqZ/pE0D292ZN4adgk5Cnr/wAqEO7WaDceozRsQz3DBvalSpcP/EL/AAaHfG0slwObn4SCRgOZ5G5jQz4nDk3lCy9D9K3X/wAWpUqjN8ikW/IQPSGOoQNHc9Vjby4GKJnAoFN1SxK7cnhdqVKjFV8Bni+txaPTjzaRDn+mvZIk78v8qVKhn5BzRgHqQKVKlTDn/9k="/></a>
                </section>
            </div>
        </div>
    </section>
    <div class="page-footer">
        <footer>
            <ul class="site-footer">
                <li id="count" class="pull-left">$count</li>
                <li id="location" class="pull-right">$location</li>
            </ul>
        </footer>
    </div>
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
            tab_color: '#8451A1',
            tab_position: 'middle-right',
            tab_inverted: false
        }]);
    </script>
</body>
</html>
HTML;

    echo $html;
}
?>