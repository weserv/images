<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Exception as VipsException;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Intent;
use Jcupitt\Vips\Interpretation;
use Jcupitt\Vips\Kernel;

/**
 * @property string $t
 * @property string $h
 * @property string $w
 * @property bool $hasAlpha
 * @property bool $isPremultiplied
 * @property int $rotation
 * @property string $tmpFileName
 * @property string $loader
 * @property string $trim
 * @property string $page
 */
class Size extends BaseManipulator
{
    /**
     * Maximum image size in pixels.
     *
     * @var int|null
     */
    protected $maxImageSize;

    /**
     * Create Size instance.
     *
     * @param int|null $maxImageSize Maximum image size in pixels.
     */
    public function __construct($maxImageSize = null)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Get the maximum image size.
     *
     * @return int|null Maximum image size in pixels.
     */
    public function getMaxImageSize()
    {
        return $this->maxImageSize;
    }

    /**
     * Set the maximum image size.
     *
     * @param int|null Maximum image size in pixels.
     */
    public function setMaxImageSize($maxImageSize)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Perform size image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        $width = $this->w;
        $height = $this->h;
        $fit = $this->getFit();

        // Check if image size is greater then the maximum allowed image size after dimension is resolved
        $this->checkImageSize($image, $width, $height);

        $image = $this->doResize($image, $fit, $width, $height);

        return $image;
    }

    /**
     * Indicating if we should not enlarge the output if the input width
     * *or* height are already less than the required dimensions
     *
     * @param  string $fit The resolved fit.
     *
     * @return bool
     */
    public function withoutEnlargement(string $fit): bool
    {
        $keys = ['fit' => 0, 'squaredown' => 1];
        if (isset($keys[$fit])) {
            return true;
        }

        return false;
    }


    /**
     * Resolve fit.
     *
     * @return string The resolved fit.
     */
    public function getFit(): string
    {
        $validFitArr = ['fit' => 0, 'fitup' => 1, 'square' => 2, 'squaredown' => 3, 'absolute' => 4, 'letterbox' => 5];
        if (isset($validFitArr[$this->t])) {
            return $this->t;
        }

        if (substr($this->t, 0, 4) === 'crop') {
            return 'crop';
        }

        return 'fit';
    }

    /**
     * Check if image size is greater then the maximum allowed image size.
     *
     * @param  Image $image The source image.
     * @param  int $width The image width.
     * @param  int $height The image height.
     *
     * @throws ImageTooLargeException if the provided image is too large for processing.
     */
    public function checkImageSize(Image $image, int $width, int $height)
    {
        if ($width === 0 && $height === 0) {
            $width = $image->width;
            $height = $image->height;
        }
        if ($width !== 0) {
            $width = $height * ($image->width / $image->height);
        }
        if ($height !== 0) {
            $height = $width / ($image->width / $image->height);
        }

        if ($this->maxImageSize) {
            $imageSize = $width * $height;

            if ($imageSize > $this->maxImageSize) {
                throw new ImageTooLargeException();
            }
        }
    }

    /**
     * Perform resize image manipulation.
     *
     * @param  Image $image The source image.
     * @param  string $fit The fit.
     * @param  int $width The width.
     * @param  int $height The height.
     *
     * @return Image The manipulated image.
     */
    public function doResize(Image $image, string $fit, int $width, int $height): Image
    {
        $inputWidth = $image->width;
        $inputHeight = $image->height;
        $rotation = $this->rotation;
        if ($rotation === 90 || $rotation === 270) {
            // Swap input output width and height when rotating by 90 or 270 degrees
            list($inputWidth, $inputHeight) = [$inputHeight, $inputWidth];
        }

        // Scaling calculations
        $xFactor = 1.0;
        $yFactor = 1.0;
        $targetResizeWidth = $width;
        $targetResizeHeight = $height;
        if ($width > 0 && $height > 0) {
            // Fixed width and height
            $xFactor = (float)($inputWidth / $width);
            $yFactor = (float)($inputHeight / $height);
            switch ($fit) {
                case 'square':
                case 'squaredown':
                case 'crop':
                    if ($xFactor < $yFactor) {
                        $targetResizeHeight = (int)round((float)($inputHeight / $xFactor));
                        $yFactor = $xFactor;
                    } else {
                        $targetResizeWidth = (int)round((float)($inputWidth / $yFactor));
                        $xFactor = $yFactor;
                    }
                    break;
                case 'letterbox':
                case 'fit':
                case 'fitup':
                    if ($xFactor > $yFactor) {
                        $targetResizeHeight = (int)round((float)($inputHeight / $xFactor));
                        $yFactor = $xFactor;
                    } else {
                        $targetResizeWidth = (int)round((float)($inputWidth / $yFactor));
                        $xFactor = $yFactor;
                    }
                    break;
                case 'absolute':
                    if ($rotation === 90 || $rotation === 270) {
                        list($xFactor, $yFactor) = [$yFactor, $xFactor];
                    }
                    break;
            }
        } elseif ($width > 0) {
            // Fixed width
            $xFactor = (float)($inputWidth / $width);
            if ($fit === 'absolute') {
                $targetResizeHeight = $height = $this->h = $inputHeight;
            } else {
                // Auto height
                $yFactor = $xFactor;
                $targetResizeHeight = $height = $this->h = (int)round((float)($inputHeight / $yFactor));
            }
        } elseif ($height > 0) {
            // Fixed height
            $yFactor = (float)($inputHeight / $height);
            if ($fit === 'absolute') {
                $targetResizeWidth = $width = $this->w = $inputWidth;
            } else {
                // Auto width
                $xFactor = $yFactor;
                $targetResizeWidth = $width = $this->w = (int)round((float)($inputWidth / $xFactor));
            }
        } else {
            // Identity transform
            $width = $this->w = $inputWidth;
            $height = $this->h = $inputHeight;
        }

        // Calculate integral box shrink
        $xShrink = max(1, (int)floor($xFactor));
        $yShrink = max(1, (int)floor($yFactor));

        // Calculate residual float affine transformation
        $xResidual = (float)($xShrink / $xFactor);
        $yResidual = (float)($yShrink / $yFactor);

        // Do not enlarge the output if the input width *and* height
        // are already less than the required dimensions
        if ($this->withoutEnlargement($fit)) {
            if ($inputWidth < $width && $inputHeight < $height) {
                $xFactor = 1.0;
                $yFactor = 1.0;
                $xShrink = 1;
                $yShrink = 1;
                $xResidual = 1.0;
                $yResidual = 1.0;
                $this->w = $inputWidth;
                $this->h = $inputHeight;
            }
        }

        // Get the current vips loader
        $loader = $this->loader;

        // If integral x and y shrink are equal, try to use shrink-on-load for JPEG, WebP, PDF and SVG
        // but not when trimming or pre-resize crop
        $shrinkOnLoad = 1;
        if ($xShrink === $yShrink && $xShrink >= 2 &&
            ($loader === 'VipsForeignLoadJpegFile' ||
                $loader === 'VipsForeignLoadWebpFile' ||
                $loader === 'VipsForeignLoadPdfFile' ||
                $loader === 'VipsForeignLoadSvgFile') &&
            !$this->trim
        ) {
            if ($xShrink >= 8) {
                $xFactor /= 8;
                $yFactor /= 8;
                $shrinkOnLoad = 8;
            } elseif ($xShrink >= 4) {
                $xFactor /= 4;
                $yFactor /= 4;
                $shrinkOnLoad = 4;
            } elseif ($xShrink >= 2) {
                $xFactor /= 2;
                $yFactor /= 2;
                $shrinkOnLoad = 2;
            }
        }
        // Help ensure a final kernel-based reduction to prevent shrink aliasing
        if ($shrinkOnLoad > 1 && ($xResidual == 1.0 || $yResidual == 1.0)) {
            $shrinkOnLoad = $shrinkOnLoad / 2;
            $xFactor = $xFactor * 2;
            $yFactor = $yFactor * 2;
        }
        if ($shrinkOnLoad > 1) {
            // Reload input using shrink-on-load
            if ($loader === 'VipsForeignLoadJpegFile') {
                // Reload JPEG file
                $image = Image::jpegload($this->tmpFileName, ['shrink' => $shrinkOnLoad]);
            } elseif ($loader === 'VipsForeignLoadWebpFile') {
                // Reload WebP file
                $image = Image::webpload($this->tmpFileName, ['shrink' => $shrinkOnLoad]);
            } elseif ($loader === 'VipsForeignLoadPdfFile') {
                // Reload PDF file
                // (don't forget to pass on the page that we want)
                $image = Image::pdfload($this->tmpFileName, [
                    'scale' => 1.0 / $shrinkOnLoad,
                    'page' => $this->page && is_numeric($this->page) && $this->page >= 0 && $this->page <= 100000 ? (int)$this->page : 0
                ]);
            } else {
                // Reload SVG file
                $image = Image::svgload($this->tmpFileName, ['scale' => 1.0 / $shrinkOnLoad]);
            }
            // Recalculate integral shrink and float residual
            $shrunkOnLoadWidth = $image->width;
            $shrunkOnLoadHeight = $image->height;
            if ($rotation === 90 || $rotation === 270) {
                // Swap input output width and height when rotating by 90 or 270 degrees
                list($shrunkOnLoadWidth, $shrunkOnLoadHeight) = [$shrunkOnLoadHeight, $shrunkOnLoadWidth];
            }
            $xFactor = (float)($shrunkOnLoadWidth) / (float)($targetResizeWidth);
            $yFactor = (float)($shrunkOnLoadHeight) / (float)($targetResizeHeight);
            $xShrink = max(1, (int)floor($xFactor));
            $yShrink = max(1, (int)floor($yFactor));
            $xResidual = (float)($xShrink) / $xFactor;
            $yResidual = (float)($yShrink) / $yFactor;
            if ($rotation === 90 || $rotation === 270) {
                list($xResidual, $yResidual) = [$yResidual, $xResidual];
            }
        }
        // Help ensure a final kernel-based reduction to prevent shrink aliasing
        if ($xShrink > 1 && $yShrink > 1 && ($xResidual == 1.0 || $yResidual == 1.0)) {
            $xShrink = $xShrink / 2;
            $yShrink = $yShrink / 2;
            $xResidual = (float)($xShrink) / $xFactor;
            $yResidual = (float)($yShrink) / $yFactor;
        }

        // Ensure we're using a device-independent colour space
        if (Utils::hasProfile($image)) {
            // Convert to sRGB using embedded profile from https://packages.debian.org/sid/all/icc-profiles-free/filelist
            try {
                $image = $image->icc_transform(__DIR__ . '/../ICC/sRGB.icc', [
                    'embedded' => true,
                    'intent' => Intent::PERCEPTUAL
                ]);
            } catch (VipsException $ignored) {
                // Ignore failure of embedded profile
            }
        } elseif ($image->interpretation === Interpretation::CMYK) {
            // Convert to sRGB using default CMYK profile from http://www.argyllcms.com/cmyk.icm
            $image = $image->icc_transform(__DIR__ . '/../ICC/sRGB.icc', [
                'input_profile' => __DIR__ . '/../ICC/cmyk.icm',
                'intent' => Intent::PERCEPTUAL
            ]);
        }

        $shouldReduce = $xResidual !== 1.0 || $yResidual !== 1.0;
        $shouldShrink = $xShrink > 1 || $yShrink > 1;
        $shouldPremultiplyAlpha = $this->hasAlpha && !$this->isPremultiplied && ($shouldReduce || $shouldShrink);

        if ($shouldPremultiplyAlpha) {
            // Premultiply image alpha channel before shrink/reduce transformations to avoid
            // dark fringing around bright pixels
            // See: http://entropymine.com/imageworsener/resizealpha/
            $image = $image->premultiply();
            $this->isPremultiplied = true;
        }

        // Fast, integral box-shrink
        if ($shouldShrink) {
            if ($yShrink > 1) {
                $image = $image->shrinkv($yShrink);
            }
            if ($xShrink > 1) {
                $image = $image->shrinkh($xShrink);
            }
            // Recalculate residual float based on dimensions of required vs shrunk images
            $shrunkWidth = $image->width;
            $shrunkHeight = $image->height;
            if ($rotation === 90 || $rotation === 270) {
                // Swap input output width and height when rotating by 90 or 270 degrees
                list($shrunkWidth, $shrunkHeight) = [$shrunkHeight, $shrunkWidth];
            }
            $xResidual = (float)($targetResizeWidth / $shrunkWidth);
            $yResidual = (float)($targetResizeHeight / $shrunkHeight);
            if ($rotation === 90 || $rotation === 270) {
                list($xResidual, $yResidual) = [$yResidual, $xResidual];
            }
        }

        // Use affine increase or kernel reduce with the remaining float part
        if ($xResidual !== 1.0 || $yResidual !== 1.0) {
            // Perform kernel-based reduction
            if ($yResidual < 1.0 || $xResidual < 1.0) {
                // Use *magick centre sampling convention instead of corner sampling
                $centreSampling = true;

                if ($yResidual < 1.0) {
                    $image = $image->reducev(1.0 / $yResidual, [
                        'kernel' => Kernel::LANCZOS3,
                        'centre' => $centreSampling
                    ]);
                }
                if ($xResidual < 1.0) {
                    $image = $image->reduceh(1.0 / $xResidual, [
                        'kernel' => Kernel::LANCZOS3,
                        'centre' => $centreSampling
                    ]);
                }
            }
            // Perform affine enlargement
            if ($yResidual > 1.0 || $xResidual > 1.0) {
                $interpolator = Image::newInterpolator('bicubic');
                if ($yResidual > 1.0 && $xResidual > 1.0) {
                    $image = $image->affine([$xResidual, 0.0, 0.0, $yResidual], ['interpolate' => $interpolator]);
                } elseif ($yResidual > 1.0) {
                    $image = $image->affine([1.0, 0.0, 0.0, $yResidual], ['interpolate' => $interpolator]);
                } elseif ($xResidual > 1.0) {
                    $image = $image->affine([$xResidual, 0.0, 0.0, 1.0], ['interpolate' => $interpolator]);
                }
            }
        }

        return $image;
    }
}
