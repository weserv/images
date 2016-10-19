<?php

namespace AndriesLouw\imagesweserv;

use AndriesLouw\imagesweserv\Exception\ImageNotValidException;
use AndriesLouw\imagesweserv\Exception\ImageTooBigException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class Client
{
    /**
     * Temp file name to download to
     *
     * @var string
     */
    protected $fileName;

    /**
     * Temp file
     *
     * @var resource
     */
    protected $handle;

    /**
     * Options for this client
     *
     * @var array
     */
    protected $options;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * @param string $fileName
     * @param array $options
     */
    public function __construct(string $fileName, array $options)
    {
        $this->fileName = $fileName;
        $this->handle = fopen($fileName, 'w');
        $this->options = $options;
        $this->initClient();
    }

    /**
     * Initialize the client
     *
     * @return void
     */
    private function initClient()
    {
        $this->client = new \GuzzleHttp\Client(
            [
                //'debug' => $this->handle,
                'connect_timeout' => $this->options['connect_timeout'],
                'decode_content' => true,
                'verify' => false,
                'allow_redirects' => [
                    'max' => $this->options['max_redirects'], // allow at most 10 redirects.
                    'strict' => false,      // use "strict" RFC compliant redirects.
                    'referer' => true,      // add a Referer header
                    'on_redirect' => function (
                        RequestInterface $request,
                        ResponseInterface $response,
                        UriInterface $uri
                    ) {
                        //trigger_error('Internal redirecting  ' . $request->getUri() . ' to ' . $uri, E_USER_NOTICE);
                    },
                    'track_redirects' => false
                ],
                'expect' => false, // Send an empty Expect header (avoids 100 responses)
                'http_errors' => true,
                'curl' => [
                    CURLOPT_FILE => $this->handle,
                    //CURLOPT_SSL_VERIFYPEER => false,
                    //CURLOPT_SSL_VERIFYHOST => false
                ],
                'on_headers' => function (ResponseInterface $response) {
                    if (!empty($this->options['allowed_mime_types']) &&
                        !isset($this->options['allowed_mime_types'][$response->getHeaderLine('Content-Type')])
                    ) {
                        $error = $this->options['error_message']['invalid_image'];
                        $str = array_pop($this->options['allowed_mime_types']);
                        $supportedImages = implode(', ', $this->options['allowed_mime_types']) . ' and ' . $str;
                        throw new ImageNotValidException(sprintf($error['message'], $supportedImages));
                    }
                    if ($this->options['max_image_size'] != 0
                        && $response->getHeaderLine('Content-Length') > $this->options['max_image_size']
                    ) {
                        $error = $this->options['error_message']['image_too_big'];
                        $size = $response->getHeaderLine('Content-Length');
                        $imageSize = $this->formatSizeUnits($size);
                        $maxImageSize = $this->formatSizeUnits($this->options['max_image_size']);
                        throw new ImageTooBigException(sprintf($error['message'], $imageSize, $maxImageSize));
                    }
                },
            ]
        );
    }

    /**
     * http://stackoverflow.com/questions/5501427/php-filesize-mb-kb-conversion
     *
     * @param  int $bytes
     *
     * @return string
     */
    private function formatSizeUnits(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @param string $url
     *
     * @throws RequestException for errors that occur during a transfer or during the on_headers event
     *
     * @return string File name
     */
    public function get(string $url): string
    {
        $requestOptions = [
            'timeout' => $this->options['timeout'],
            'headers' => [
                'Accept-Encoding' => 'gzip',
                'User-Agent' => $this->options['user_agent'],
            ]
        ];

        try {
            /**
             * @var ResponseInterface $response
             */
            $this->client->get($url, $requestOptions);
        } catch (RequestException $e) {
            @fclose($this->handle);
            $previousException = $e->getPrevious();
            if ($previousException instanceof ImageNotValidException
                || $previousException instanceof ImageTooBigException
            ) {
                if ($previousException instanceof ImageNotValidException) {
                    $error = $this->options['error_message']['invalid_image'];
                    trigger_error(sprintf($error['log'], $url), E_USER_WARNING);
                } else {
                    if ($previousException instanceof ImageTooBigException) {
                        $error = $this->options['error_message']['image_too_big'];
                        trigger_error(sprintf($error['log'], $url), E_USER_WARNING);
                    }
                }
            } else {
                $error = $this->options['error_message']['curl_error'];

                trigger_error(
                    sprintf(
                        $error['log'],
                        $e->getMessage(),
                        $url,
                        ($e->hasResponse() && $e->getResponse() != null ?
                            $e->getResponse()->getStatusCode()
                            : $e->getCode())
                    ),
                    E_USER_WARNING
                );
            }
            throw $e;
        }

        fclose($this->handle);

        return $this->fileName;
    }
}
