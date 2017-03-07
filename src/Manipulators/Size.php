<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Access;
use Jcupitt\Vips\Extend;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Kernel;

/**
 * @property string $t
 * @property string $a
 * @property string $h
 * @property string $w
 * @property string $bg
 * @property bool $hasAlpha
 * @property bool $is16Bit
 * @property bool $isPremultiplied
 * @property int $rotation
 * @property string $accessMethod
 * @property string $tmpFileName
 * @property string $trim
 * @property array|null $cropCoordinates
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
        $width = $this->getWidth();
        $height = $this->getHeight();
        $fit = $this->getFit();

        // Check if image size is greater then the maximum allowed image size after dimension is resolved
        $this->checkImageSize($image, $width, $height);

        $image = $this->doResize($image, $fit, $width, $height);

        return $image;
    }

    /**
     * Resolve width.
     *
     * @return int The resolved width.
     */
    public function getWidth(): int
    {
        if (!is_numeric($this->w)) {
            return 0;
        }

        if ($this->w <= 0) {
            return 0;
        }

        return (int)$this->w;
    }

    /**
     * Resolve height.
     *
     * @return int The resolved height.
     */
    public function getHeight(): int
    {
        if (!is_numeric($this->h)) {
            return 0;
        }

        if ($this->h <= 0) {
            return 0;
        }

        return (int)$this->h;
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

        $validIndividualCropArr = ['top' => 0, 'left' => 1, 'center' => 2, 'right' => 3, 'bottom' => 4];
        $validCropArr = ['top' => 0, 'bottom' => 1];
        $validPositionArr = ['left' => 0, 'right' => 1];
        $splitFit = explode('-', $this->t);

        if (isset($splitFit[0]) && $splitFit[0] === 'crop' && isset($splitFit[1]) && !isset($splitFit[3]) && (
                (isset($validIndividualCropArr[$splitFit[1]]) && !isset($splitFit[2])) ||
                (isset($splitFit[2]) && isset($validCropArr[$splitFit[1]]) && isset($validPositionArr[$splitFit[2]])) ||
                (isset($splitFit[2]) && is_numeric($splitFit[1]) && is_numeric($splitFit[2]))
            )
        ) {
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
     * Resolve the crop resize dimensions.
     *
     * @param  Image $image The source image.
     * @param  int $width The width.
     * @param  int $height The height.
     *
     * @return array   The resize dimensions.
     */
    public function resolveCropResizeDimensions(Image $image, int $width, int $height): array
    {
        if ($height > $width * ($image->height / $image->width)) {
            return [$height * ($image->width / $image->height), $height];
        }

        return [$width, $width * ($image->height / $image->width)];
    }

    /**
     * Resolve the crop offset.
     *
     * @param  Image $image The source image.
     * @param  int $width The width.
     * @param  int $height The height.
     *
     * @return array The crop offset.
     */
    public function resolveCropOffset(Image $image, int $width, int $height): array
    {
        list($offsetPercentageX, $offsetPercentageY) = $this->getCrop();

        $offsetX = (int)(($image->width * $offsetPercentageX / 100) - ($width / 2));
        $offsetY = (int)(($image->height * $offsetPercentageY / 100) - ($height / 2));

        $maxOffsetX = $image->width - $width;
        $maxOffsetY = $image->height - $height;

        if ($offsetX < 0) {
            $offsetX = 0;
        }

        if ($offsetY < 0) {
            $offsetY = 0;
        }

        if ($offsetX > $maxOffsetX) {
            $offsetX = $maxOffsetX;
        }

        if ($offsetY > $maxOffsetY) {
            $offsetY = $maxOffsetY;
        }

        return [$offsetX, $offsetY];
    }

    /**
     * Resolve crop.
     *
     * @return array The resolved crop.
     */
    public function getCrop(): array
    {
        $cropMethods = [
            'top-left' => [0, 0],
            't' => [50, 0], // Deprecated use top instead
            'top' => [50, 0],
            'top-right' => [100, 0],
            'l' => [0, 50], // Deprecated use left instead
            'left' => [0, 50],
            'center' => [50, 50],
            'r' => [0, 50], // Deprecated use right instead
            'right' => [100, 50],
            'bottom-left' => [0, 100],
            'b' => [50, 100], // Deprecated use bottom instead
            'bottom' => [50, 100],
            'bottom-right' => [100, 100],
        ];

        if (isset($cropMethods[$this->a])) {
            return $cropMethods[$this->a];
        }

        $matches = explode('-', $this->a);

        if (isset($matches[0]) && $matches[0] === 'crop' && isset($matches[1]) && isset($matches[2]) && !isset($matches[3])
            && is_numeric($matches[1]) && is_numeric($matches[2])
        ) {
            if ($matches[1] > 100 || $matches[2] > 100) {
                return [50, 50];
            }

            return [
                (int)$matches[1],
                (int)$matches[2],
            ];
        }

        return [50, 50];
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
        } else {
            if ($width > 0) {
                // Fixed width
                $xFactor = (float)($inputWidth / $width);
                if ($fit === 'absolute') {
                    $targetResizeHeight = $height = $inputHeight;
                } else {
                    // Auto height
                    $yFactor = $xFactor;
                    $targetResizeHeight = $height = (int)round((float)($inputHeight / $yFactor));
                }
            } else {
                if ($height > 0) {
                    // Fixed height
                    $yFactor = (float)($inputHeight / $height);
                    if ($fit === 'absolute') {
                        $targetResizeWidth = $width = $inputWidth;
                    } else {
                        // Auto width
                        $xFactor = $yFactor;
                        $targetResizeWidth = $width = (int)round((float)($inputWidth / $xFactor));
                    }
                } else {
                    // Identity transform
                    $width = $inputWidth;
                    $height = $inputHeight;
                }
            }
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
                $width = $inputWidth;
                $height = $inputHeight;
            }
        }

        // Get the current vips loader
        $loader = $image->typeof(Utils::VIPS_META_LOADER) !== 0 ? $image->get(Utils::VIPS_META_LOADER) : 'unknown';

        // If integral x and y shrink are equal, try to use shrink-on-load for JPEG, WebP, PDF and SVG
        // but not when trimming or pre-resize crop
        $shrinkOnLoad = 1;
        if ($xShrink === $yShrink && $xShrink >= 2 &&
            ($loader === 'jpegload' || $loader === 'webpload' || $loader === 'pdfload' || $loader === 'svgload') &&
            !$this->trim && !$this->cropCoordinates
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

        if ($shrinkOnLoad > 1) {
            // Reload input using shrink-on-load
            if ($loader === 'jpegload') {
                // Reload JPEG file
                $image = Image::jpegload($this->tmpFileName, ['shrink' => $shrinkOnLoad]);
            } elseif ($loader === 'webpload') {
                // Reload WebP file
                $image = Image::webpload($this->tmpFileName, ['shrink' => $shrinkOnLoad]);
            } elseif ($loader === 'pdfload') {
                // Reload PDF file
                // (don't forget to pass on the page that we want)
                $image = Image::pdfload($this->tmpFileName, [
                    'scale' => 1.0 / $shrinkOnLoad,
                    'page' => $this->page && is_numeric($this->page) ? (int)$this->page : 0
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

        $shouldReduce = $xResidual != 1.0 || $yResidual != 1.0;
        $shouldShrink = $xShrink > 1 || $yShrink > 1;
        $shouldPremultiplyAlpha = $this->hasAlpha && !$this->isPremultiplied && ($shouldReduce || $shouldShrink);

        if ($shouldPremultiplyAlpha) {
            // Premultiply image alpha channel before all transformations to avoid
            // dark fringing around bright pixels
            // See: http://entropymine.com/imageworsener/resizealpha/
            $image = $image->premultiply();
            $this->isPremultiplied = true;
        }

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
            $xResidual = (float)($targetResizeWidth / $shrunkWidth);
            $yResidual = (float)($targetResizeHeight / $shrunkHeight);
        }

        // Use affine increase or kernel reduce with the remaining float part
        if ($xResidual != 1.0 || $yResidual != 1.0) {
            // Insert line cache to prevent over-computation of previous operations
            if ($this->accessMethod === Access::SEQUENTIAL) {
                // TODO Figure out how many scanline(s) ('tile_height') it will need.
                $image = $image->linecache([
                    'tile_height' => 10,
                    'access' => Access::SEQUENTIAL,
                    'threaded' => true
                ]);
            }

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
                if ($yResidual > 1.0) {
                    $image = $image->affine([1.0, 0.0, 0.0, $yResidual]/*, ['interpolate' => 'bicubic']*/);
                }
                if ($xResidual > 1.0) {
                    $image = $image->affine([$xResidual, 0.0, 0.0, 1.0]/*, ['interpolate' => 'bicubic']*/);
                }
            }
        }

        if ($image->width != $width || $image->height != $height) {
            if ($fit === 'letterbox') {
                if ($this->bg) {
                    $backgroundColor = (new Color($this->bg))->toRGBA();
                } else {
                    $backgroundColor = [
                        0,
                        0,
                        0,
                        0
                    ];
                }

                // Scale up 8-bit values to match 16-bit input image
                $multiplier = $this->is16Bit ? 256 : 1;

                // Create background colour
                if ($image->bands > 2) {
                    $background = [
                        $multiplier * $backgroundColor[0],
                        $multiplier * $backgroundColor[1],
                        $multiplier * $backgroundColor[2]
                    ];
                } else {
                    // Convert sRGB to greyscale
                    $background = [
                        $multiplier * (
                            (0.2126 * $backgroundColor[0]) +
                            (0.7152 * $backgroundColor[1]) +
                            (0.0722 * $backgroundColor[2])
                        )
                    ];
                }

                $hasAlpha = $this->hasAlpha;

                // Add alpha channel to background colour
                if ($backgroundColor[3] < 255 || $hasAlpha) {
                    array_push($background, $backgroundColor[3] * $multiplier);
                }

                // Add non-transparent alpha channel, if required
                if ($backgroundColor[3] < 255 && !$hasAlpha) {
                    $pixel = Image::black(1, 1)->add(255 * $multiplier)->cast($image->format);
                    $result = $pixel->embed(0, 0, $image->width, $image->height, ['extend' => Extend::COPY]);
                    $result->interpretation = $image->interpretation;

                    $image = $image->bandjoin($result);

                    // Image has now a alpha channel. Useful for the next manipulators.
                    $this->hasAlpha = true;
                }

                $left = (int)round(($width - $image->width) / 2);
                $top = (int)round(($height - $image->height) / 2);
                $image = $image->embed(
                    $left,
                    $top,
                    $width,
                    $height,
                    ['extend' => Extend::BACKGROUND, 'background' => $background]
                );
            } else {
                $cropArr = ['square' => 0, 'squaredown' => 1, 'crop' => 2];
                if (isset($cropArr[$fit])) {
                    list($offsetX, $offsetY) = $this->resolveCropOffset($image, $width, $height);
                    $width = min($image->width, $width);
                    $height = min($image->height, $height);

                    $image = $image->extract_area($offsetX, $offsetY, $width, $height);
                }
            }
        }

        return $image;
    }
}
