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
        $width = (int)$this->w;
        $height = (int)$this->h;
        $fit = $this->getFit();

        if ($width <= 0) {
            $width = 0;
        }
        if ($height <= 0) {
            $height = 0;
        }

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
        return $fit === 'fit' || $fit === 'squaredown';
    }

    /**
     * Resolve fit.
     *
     * @return string The resolved fit.
     */
    public function getFit(): string
    {
        if ($this->t === 'fit' ||
            $this->t === 'fitup' ||
            $this->t === 'square' ||
            $this->t === 'squaredown' ||
            $this->t === 'absolute' ||
            $this->t === 'letterbox'
        ) {
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
        // Set width and height to zero if it's invalid
        // Otherwise cast it to a integer
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
                throw new ImageTooLargeException('Image is too large for processing. Width x Height should be less than 70 megapixels.');
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
            // Convert to sRGB using embedded profile
            // https://packages.debian.org/sid/all/icc-profiles-free/filelist
            $thumbnailOptions['export_profile'] = __DIR__ . '/../ICC/sRGB.icc';
        } elseif ($image->interpretation === Interpretation::CMYK) {
            // CMYK with no embedded profile, import using default CMYK profile
            // http://www.argyllcms.com/cmyk.icm
            $thumbnailOptions['import_profile'] = __DIR__ . '/../ICC/cmyk.icm';
            // Always convert to sRGB
            // https://packages.debian.org/sid/all/icc-profiles-free/filelist
            $thumbnailOptions['export_profile'] = __DIR__ . '/../ICC/sRGB.icc';
        }

        $inputWidth = $image->width;
        $inputHeight = $image->height;

        $orientation = $this->or;
        $exifOrientation = Utils::resolveExifOrientation($image);
        $userRotate = $orientation === '90' || $orientation === '270';
        $exifRotate = $exifOrientation === 90 || $exifOrientation === 270;

        if ($userRotate xor $exifRotate) {
            // Swap input width and height when rotating by 90 or 270 degrees
            list($inputWidth, $inputHeight) = [$inputHeight, $inputWidth];
        }

        $cropPosition = $this->a;

        // Scaling calculations
        $targetResizeWidth = $width;
        $targetResizeHeight = $height;

        // Is smart crop? Only when a fixed width and height is specified.
        $isSmartCrop = $width > 0 && $height > 0 && ($cropPosition === Interesting::ENTROPY || $cropPosition === Interesting::ATTENTION);

        if ($isSmartCrop) {
            // Set crop option
            $thumbnailOptions['crop'] = $cropPosition;
        } elseif ($width > 0 && $height > 0) {
            // Fixed width and height
            $xFactor = (float)($inputWidth / $width);
            $yFactor = (float)($inputHeight / $height);
            switch ($fit) {
                case 'square':
                case 'squaredown':
                case 'crop':
                    if ($xFactor < $yFactor) {
                        $targetResizeHeight = (int)round((float)($inputHeight / $xFactor));
                    } else {
                        $targetResizeWidth = (int)round((float)($inputWidth / $yFactor));
                    }
                    break;
                case 'letterbox':
                case 'fit':
                case 'fitup':
                    if ($xFactor > $yFactor) {
                        $targetResizeHeight = (int)round((float)($inputHeight / $xFactor));
                    } else {
                        $targetResizeWidth = (int)round((float)($inputWidth / $yFactor));
                    }
                    break;
            }
        } elseif ($width > 0) {
            // Fixed width
            if ($fit === 'absolute') {
                $targetResizeHeight = $this->h = $inputHeight;
            } else {
                // Auto height
                $yFactor = (float)($inputWidth / $width);
                $targetResizeHeight = $this->h = (int)round((float)($inputHeight / $yFactor));
            }
        } elseif ($height > 0) {
            // Fixed height
            if ($fit === 'absolute') {
                $targetResizeWidth = $this->w = $inputWidth;
            } else {
                // Auto width
                $xFactor = (float)($inputHeight / $height);
                $targetResizeWidth = $this->w = (int)round((float)($inputWidth / $xFactor));
            }
        } else {
            // Identity transform
            $targetResizeWidth = $this->w = $inputWidth;
            $targetResizeHeight = $this->h = $inputHeight;
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

        // Note: Mocking on static methods isn't possible, so we don't use `Image::`.
        return $this->trim ?
            $image->thumbnail_image($targetResizeWidth, $thumbnailOptions) :
            $image->thumbnail($this->tmpFileName, $targetResizeWidth, $thumbnailOptions);
    }
}
