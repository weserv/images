<?php

namespace Weserv\Images\Manipulators;

use Jcupitt\Vips\Image;
use Jcupitt\Vips\Intent;
use Jcupitt\Vips\Interpretation;
use Jcupitt\Vips\Size;
use Weserv\Images\Exception\ImageTooLargeException;
use Weserv\Images\Manipulators\Helpers\Utils;

/**
 * @property string $t
 * @property string|int $w
 * @property string|int $h
 * @property string $dpr
 * @property string $a
 * @property string $tmpFileName
 * @property string $page
 * @property int $rotation
 * @property bool $flip
 * @property bool $flop
 * @property string|bool $trim
 * @property string $gam
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Thumbnail extends BaseManipulator
{
    /**
     * Maximum image size in pixels.
     */
    protected ?int $maxImageSize;

    /**
     * Profile map to ensure that we use a device-
     * independent color space for the images we process.
     */
    protected array $profileMap = [
        Interpretation::SRGB => 'srgb',
        Interpretation::CMYK => 'cmyk'
    ];

    protected const VIPS_MAX_COORD = 10000000;

    /**
     * Create Thumbnail instance.
     *
     * @param int|null $maxImageSize Maximum image size in pixels.
     */
    public function __construct(?int $maxImageSize = null)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Get the maximum image size.
     *
     * @return int|null Maximum image size in pixels.
     */
    public function getMaxImageSize(): ?int
    {
        return $this->maxImageSize;
    }

    /**
     * Set the maximum image size.
     *
     * @param int|null $maxImageSize Maximum image size in pixels.
     */
    public function setMaxImageSize(?int $maxImageSize): void
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Perform thumbnail image manipulation.
     *
     * @param Image $image The source image.
     *
     * @throws \Jcupitt\Vips\Exception
     * @throws ImageTooLargeException if the provided image is too large for processing.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        // Cast any strings to integers
        $this->w = (int)$this->w;
        $this->h = (int)$this->h;

        // Width and height should never be less than zero
        if ($this->w < 0) {
            $this->w = 0;
        }
        if ($this->h < 0) {
            $this->h = 0;
        }

        [$this->w, $this->h] = $this->applyDpr($this->w, $this->h, $this->getDpr());

        // Check if image size is greater then the maximum allowed image size after dimension is resolved
        $this->checkImageSize($image, $this->w, $this->h);

        return $this->doThumbnail($image, $this->getFit(), $this->w, $this->h);
    }

    /**
     * Indicating if we should not enlarge the output if the input width
     * *and* height are already less than the required dimensions
     *
     * @param string $fit The resolved fit.
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

        if ($this->t !== null && strpos($this->t, 'crop') === 0) {
            return 'crop';
        }

        return 'fit';
    }

    /**
     * Resolve the device pixel ratio.
     *
     * @return float The device pixel ratio.
     */
    public function getDpr(): float
    {
        if (!is_numeric($this->dpr)) {
            return 1.0;
        }

        if ($this->dpr < 0 || $this->dpr > 8) {
            return 1.0;
        }

        return (float)$this->dpr;
    }

    /**
     * Apply the device pixel ratio.
     *
     * @param int $width The target image width.
     * @param int $height The target image height.
     * @param float $dpr The device pixel ratio.
     *
     * @return int[] The modified width and height.
     */
    public function applyDpr(int $width, int $height, float $dpr): array
    {
        $width *= $dpr;
        $height *= $dpr;

        return [
            (int)$width,
            (int)$height,
        ];
    }

    /**
     * Check if image size is greater then the maximum allowed image size.
     *
     * @param Image $image The source image.
     * @param int $width The image width.
     * @param int $height The image height.
     *
     * @throws ImageTooLargeException if the provided image is too large for processing.
     *
     * @return void
     */
    public function checkImageSize(Image $image, int $width, int $height): void
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

        if ($this->maxImageSize !== null) {
            $imageSize = $width * $height;

            if ($imageSize > $this->maxImageSize) {
                $error = 'Image is too large for processing. Width x Height should be less than 70 megapixels.';
                throw new ImageTooLargeException($error);
            }
        }
    }

    /**
     * Perform thumbnail image manipulation.
     *
     * @param Image $image The source image.
     * @param string $fit The fit.
     * @param int $width The width.
     * @param int $height The height.
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function doThumbnail(Image $image, string $fit, int $width, int $height): Image
    {
        // Default settings
        $thumbnailOptions = [
            'auto_rotate' => false,
            'linear' => false
        ];

        $isCMYK = $image->interpretation === Interpretation::CMYK;
        $embeddedProfile = Utils::hasProfile($image);

        // Ensure we're using a device-independent color space
        if ($embeddedProfile || $isCMYK) {
            // Embedded profile; fallback in case the profile embedded in the image is broken.
            // No embedded profile; import using default CMYK profile.
            $thumbnailOptions['import_profile'] = $isCMYK ?
                $this->profileMap[Interpretation::CMYK] :
                $this->profileMap[Interpretation::SRGB];

            // Convert to sRGB using embedded or import profile.
            $thumbnailOptions['export_profile'] = $this->profileMap[Interpretation::SRGB];

            // Use "perceptual" intent to better match imagemagick.
            $thumbnailOptions['intent'] = Intent::PERCEPTUAL;
        }

        $inputWidth = $image->width;
        $inputHeight = $image->height;

        $rotation = $this->rotation;
        $swapNeeded = $rotation === 90 || $rotation === 270;

        if ($swapNeeded) {
            // Swap input width and height when rotating by 90 or 270 degrees
            [$inputWidth, $inputHeight] = [$inputHeight, $inputWidth];
        }

        // Scaling calculations
        $targetResizeWidth = $width;
        $targetResizeHeight = $height;

        if ($width > 0 && $height > 0) { // Fixed width and height
            $xFactor = (float)($inputWidth / $width);
            $yFactor = (float)($inputHeight / $height);
            switch ($fit) {
                case 'square':
                case 'squaredown':
                case 'crop':
                    if ($xFactor < $yFactor) {
                        $targetResizeHeight = (int)round($inputHeight / $xFactor);
                    } else {
                        $targetResizeWidth = (int)round($inputWidth / $yFactor);
                    }
                    break;
                case 'letterbox':
                case 'fit':
                case 'fitup':
                    if ($xFactor > $yFactor) {
                        $targetResizeHeight = (int)round($inputHeight / $xFactor);
                    } else {
                        $targetResizeWidth = (int)round($inputWidth / $yFactor);
                    }
                    break;
            }
        } elseif ($width > 0) { // Fixed width
            if ($fit === 'absolute') {
                $targetResizeHeight = $this->h = $inputHeight;
            } else {
                // Auto height
                $yFactor = (float)($inputWidth / $width);
                $this->h = (int)round($inputHeight / $yFactor);

                // Height is missing, replace with a huuuge value to prevent
                // reduction or enlargement in that axis
                $targetResizeHeight = self::VIPS_MAX_COORD;
            }
        } elseif ($height > 0) { // Fixed height
            if ($fit === 'absolute') {
                $targetResizeWidth = $this->w = $inputWidth;
            } else {
                // Auto width
                $xFactor = (float)($inputHeight / $height);
                $this->w = (int)round($inputWidth / $xFactor);

                // Width is missing, replace with a huuuge value to prevent
                // reduction or enlargement in that axis
                $targetResizeWidth = self::VIPS_MAX_COORD;
            }
        } else {
            // Identity transform
            $targetResizeWidth = $this->w = $inputWidth;
            $targetResizeHeight = $this->h = $inputHeight;
        }

        if ($swapNeeded) {
            // Swap target output width and height when rotating by 90 or 270 degrees
            [$targetResizeWidth, $targetResizeHeight] = [$targetResizeHeight, $targetResizeWidth];
        }

        // Assign settings
        $thumbnailOptions['height'] = $targetResizeHeight;

        if ($fit === 'absolute') {
            $thumbnailOptions['size'] = Size::FORCE;
        } else {
            $thumbnailOptions['size'] = $this->withoutEnlargement($fit) ? Size::DOWN : Size::BOTH;
        }

        /**
         * Try to use shrink-on-load for JPEG and WebP, when not
         * applying gamma correction or when trimming isn't required.
         *
         * Note: After this operation the pixel interpretation is sRGB or RGB
         *
         * @see Interpretation::SRGB
         * @see Interpretation::RGB
         */
        return $this->trim !== false || isset($this->gam) ?
            $image->thumbnail_image($targetResizeWidth, $thumbnailOptions) :
            Image::thumbnail($this->tmpFileName, $targetResizeWidth, $thumbnailOptions);
    }
}
