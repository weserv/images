<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interesting;
use Jcupitt\Vips\Interpretation;
use Jcupitt\Vips\Size;

/**
 * @property string $t
 * @property string $w
 * @property string $h
 * @property string $a
 * @property string $tmpFileName
 * @property string $page
 * @property string $or
 * @property string $trim
 * @property array|bool $trimCoordinates
 */
class Thumbnail extends BaseManipulator
{
    /**
     * Maximum image size in pixels.
     *
     * @var int|null
     */
    protected $maxImageSize;

    /**
     * Create Thumbnail instance.
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
     * Perform thumbnail image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @throws ImageTooLargeException if the provided image is too large for processing.
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

        $image = $this->doThumbnail($image, $fit, $width, $height);

        return $image;
    }

    /**
     * Indicating if we should not enlarge the output if the input width
     * *and* height are already less than the required dimensions
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

        if (strpos($this->t, 'crop') === 0) {
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
            $width = (int)($height * ($image->width / $image->height));
        }
        if ($height !== 0) {
            $height = (int)($width / ($image->width / $image->height));
        }

        if ($this->maxImageSize) {
            $imageSize = $width * $height;

            if ($imageSize > $this->maxImageSize) {
                throw new ImageTooLargeException();
            }
        }
    }

    /**
     * Perform thumbnail image manipulation.
     *
     * @param  Image $image The source image.
     * @param  string $fit The fit.
     * @param  int $width The width.
     * @param  int $height The height.
     *
     * @return Image The manipulated image.
     */
    public function doThumbnail(Image $image, string $fit, int $width, int $height): Image
    {
        // Default settings
        $thumbnailOptions = [
            'auto_rotate' => true,
            'linear' => false
        ];

        // Ensure we're using a device-independent colour space
        if (Utils::hasProfile($image)) {
            // Convert to sRGB using embedded profile from https://packages.debian.org/sid/all/icc-profiles-free/filelist
            $thumbnailOptions['export_profile'] = __DIR__ . '/../ICC/sRGB.icc';
        } elseif ($image->interpretation === Interpretation::CMYK) {
            // Import using default CMYK profile from http://www.argyllcms.com/cmyk.icm
            $thumbnailOptions['import_profile'] = __DIR__ . '/../ICC/cmyk.icm';
        }

        $trimCoordinates = $this->trimCoordinates;

        $inputWidth = $image->width;
        $inputHeight = $image->height;
        $imageTrimWidth = $inputWidth;
        $imageTrimHeight = $inputHeight;
        if ($trimCoordinates) {
            list(, , $imageTrimWidth, $imageTrimHeight) = $trimCoordinates;
        }

        $orientation = $this->or;
        $exifOrientation = Utils::resolveExifOrientation($image);
        $userRotate = $orientation === '90' || $orientation === '270';
        $exifRotate = $exifOrientation === 90 || $exifOrientation === 270;

        if ($userRotate xor $exifRotate) {
            // Swap input/trim width and height when rotating by 90 or 270 degrees
            list($inputWidth, $inputHeight) = [$inputHeight, $inputWidth];
            list($imageTrimWidth, $imageTrimHeight) = [$imageTrimHeight, $imageTrimWidth];
        }

        $cropPosition = $this->a;

        // Scaling calculations
        $xFactor = 1.0;
        $yFactor = 1.0;
        $xFactorTrim = 1.0;
        $yFactorTrim = 1.0;
        $targetResizeWidth = $width;
        $targetResizeHeight = $height;

        // Is smart crop? Only when a fixed width and height is specified.
        if ($width > 0 && $height > 0 && ($cropPosition === Interesting::ENTROPY || $cropPosition === Interesting::ATTENTION)) {
            // Set crop option
            $thumbnailOptions['crop'] = $cropPosition;
        } elseif ($width > 0 && $height > 0) {
            // Fixed width and height
            $xFactor = (float)($inputWidth / $width);
            $yFactor = (float)($inputHeight / $height);
            if ($trimCoordinates) {
                $xFactorTrim = (float)($imageTrimWidth / $width);
                $yFactorTrim = (float)($imageTrimHeight / $height);
            }
            switch ($fit) {
                case 'square':
                case 'squaredown':
                case 'crop':
                    if ($xFactor < $yFactor) {
                        $targetResizeHeight = (int)round((float)($inputHeight / $xFactor));
                        $yFactor = $xFactor;
                        $yFactorTrim = $xFactorTrim;
                    } else {
                        $targetResizeWidth = (int)round((float)($inputWidth / $yFactor));
                        $xFactor = $yFactor;
                        $xFactorTrim = $yFactorTrim;
                    }
                    break;
                case 'letterbox':
                case 'fit':
                case 'fitup':
                    if ($xFactor > $yFactor) {
                        $targetResizeHeight = (int)round((float)($inputHeight / $xFactor));
                        $yFactor = $xFactor;
                        $yFactorTrim = $xFactorTrim;
                    } else {
                        $targetResizeWidth = (int)round((float)($inputWidth / $yFactor));
                        $xFactor = $yFactor;
                        $xFactorTrim = $yFactorTrim;
                    }
                    break;
            }
        } elseif ($width > 0) {
            // Fixed width
            $xFactor = (float)($inputWidth / $width);
            if ($trimCoordinates) {
                $xFactorTrim = (float)($imageTrimWidth / $width);
            }
            if ($fit === 'absolute') {
                $targetResizeHeight = $this->h = $inputHeight;
            } else {
                // Auto height
                $yFactor = $xFactor;
                $targetResizeHeight = $this->h = (int)round((float)($inputHeight / $yFactor));
                $yFactorTrim = $xFactorTrim;
            }
        } elseif ($height > 0) {
            // Fixed height
            $yFactor = (float)($inputHeight / $height);
            if ($trimCoordinates) {
                $yFactorTrim = (float)($imageTrimHeight / $height);
            }
            if ($fit === 'absolute') {
                $targetResizeWidth = $this->w = $inputWidth;
            } else {
                // Auto width
                $xFactor = $yFactor;
                $targetResizeWidth = $this->w = (int)round((float)($inputWidth / $xFactor));
                $xFactorTrim = $yFactorTrim;
            }
        } else {
            // Identity transform
            $targetResizeWidth = $this->w = $inputWidth;
            $targetResizeHeight = $this->h = $inputHeight;
        }

        if ($trimCoordinates) {
            $targetResizeWidthTrim = (int)round((float)($inputWidth / $xFactorTrim));
            $targetResizeHeightTrim = (int)round((float)($inputHeight / $yFactorTrim));

            $xFactorTrim = (float)($targetResizeWidthTrim / $targetResizeWidth);
            $yFactorTrim = (float)($targetResizeHeightTrim / $targetResizeHeight);

            $xFactor /= $xFactorTrim;
            $yFactor /= $yFactorTrim;

            if ($fit === 'absolute' && ($width > 0 || $height > 0)) {
                if ($width > 0 && $height > 0) {
                    $imageTargetWidth = $targetResizeWidth;
                    $imageTargetHeight = $targetResizeHeight;
                } elseif ($width > 0) {
                    $imageTargetWidth = $targetResizeWidth;
                    $imageTargetHeight = (int)round((float)($imageTrimHeight / $yFactor));

                } else {
                    $imageTargetHeight = $targetResizeHeight;
                    $imageTargetWidth = (int)round((float)($imageTrimWidth / $xFactor));
                }

                if ($userRotate xor $exifRotate) {
                    list($xFactor, $yFactor) = [$yFactor, $xFactor];
                }
            } else {
                $imageTargetWidth = (int)round((float)($imageTrimWidth / $xFactor));
                $imageTargetHeight = (int)round((float)($imageTrimHeight / $yFactor));
            }

            if ($userRotate xor $exifRotate) {
                // Swap target width and height when rotating by 90 or 270 degrees
                list($imageTargetWidth, $imageTargetHeight) = [$imageTargetHeight, $imageTargetWidth];
            }

            if ($fit !== 'absolute') {
                if ($width > 0 && $height === 0) {
                    $this->h = $imageTargetHeight;
                } elseif ($height > 0 && $width === 0) {
                    $this->w = $imageTargetWidth;
                }
            }

            $leftTrim = (int)round((float)($trimCoordinates[0] / $xFactor));
            $topTrim = (int)round((float)($trimCoordinates[1] / $yFactor));

            $trimCoordinates = [
                $leftTrim,
                $topTrim,
                $imageTargetWidth,
                $imageTargetHeight,
            ];

            $targetResizeWidth *= $xFactorTrim;
            $targetResizeHeight *= $yFactorTrim;
        }

        if ($userRotate) {
            // Swap target output width and height when rotating by 90 or 270 degrees
            // Note: EXIF orientation is handled in the thumbnail operator
            // so it's not necessary to swap it here.
            list($targetResizeWidth, $targetResizeHeight) = [$targetResizeHeight, $targetResizeWidth];
        }

        // Assign settings
        $thumbnailOptions['height'] = $targetResizeHeight;

        if ($fit === 'absolute') {
            $thumbnailOptions['size'] = 'force';
        } else {
            $thumbnailOptions['size'] = $this->withoutEnlargement($fit) ? Size::DOWN : Size::BOTH;
        }

        // Mocking on static methods isn't possible, so we don't use `Image::`.
        $thumbnailImage = $image->thumbnail($this->tmpFileName, $targetResizeWidth, $thumbnailOptions);

        if ($trimCoordinates) {
            $shrunkWidth = $thumbnailImage->width;
            $shrunkHeight = $thumbnailImage->height;
            $xFactor = (float)$shrunkWidth / (float)$targetResizeWidth;
            $yFactor = (float)$shrunkHeight / (float)$targetResizeHeight;
            $xShrink = max(1, (int)floor($xFactor));
            $yShrink = max(1, (int)floor($yFactor));
            $xResidual = (float)$xShrink / $xFactor;
            $yResidual = (float)$yShrink / $yFactor;

            $this->trimCoordinates = [
                (int)round((float)($trimCoordinates[0] / $xResidual)),
                (int)round((float)($trimCoordinates[1] / $yResidual)),
                (int)round((float)($trimCoordinates[2] / $xResidual)),
                (int)round((float)($trimCoordinates[3] / $yResidual)),
            ];
        }

        return $thumbnailImage;
    }
}
