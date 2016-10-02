<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $t
 * @property string $a
 * @property string $h
 * @property string $w
 */
class Size extends BaseManipulator
{
    /**
     * Maximum image size in pixels.
     * @var integer|null
     */
    protected $maxImageSize;

    /**
     * Create Size instance.
     * @param integer|null $maxImageSize Maximum image size in pixels.
     */
    public function __construct($maxImageSize = null)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Get the maximum image size.
     * @return integer|null Maximum image size in pixels.
     */
    public function getMaxImageSize()
    {
        return $this->maxImageSize;
    }

    /**
     * Set the maximum image size.
     * @param integer|null Maximum image size in pixels.
     */
    public function setMaxImageSize($maxImageSize)
    {
        $this->maxImageSize = $maxImageSize;
    }

    /**
     * Perform size image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
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
     * @return integer The resolved width.
     */
    public function getWidth()
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
     * @return integer The resolved height.
     */
    public function getHeight()
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
     * @param string $fit The resolved fit.
     * @return bool
     */
    public function withoutEnlargement($fit)
    {
        if (in_array($fit, ['fit', 'squaredown'], true)) {
            return true;
        }

        return false;
    }


    /**
     * Resolve fit.
     * @return string The resolved fit.
     */
    public function getFit()
    {
        if (in_array($this->t, ['fit', 'fitup', 'square', 'squaredown', 'absolute', 'letterbox'], true)) {
            return $this->t;
        }

        if (preg_match('/^(crop)(-top-left|-top|-top-right|-left|-center|-right|-bottom-left|-bottom|-bottom-right|-[\d]{1,3}-[\d]{1,3})*$/',
            $this->t)) {
            return 'crop';
        }

        return 'fit';
    }

    /**
     * Check if image size is greater then the maximum allowed image size.
     * @param  Image $image The source image.
     * @param  double $width The image width.
     * @param  double $height The image height.
     * @throws \Exception if the provided image is too large for processing.
     */
    public function checkImageSize($image, $width, $height)
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

        if ($this->maxImageSize !== null) {
            $imageSize = $width * $height;

            if ($imageSize > $this->maxImageSize) {
                throw new ImageTooLargeException('Image is too large for processing. Width x Height should be less than 70 megapixels.');
            }
        }
    }

    /**
     * Resolve the crop resize dimensions.
     * @param  Image $image The source image.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return array   The resize dimensions.
     */
    public function resolveCropResizeDimensions(Image $image, $width, $height)
    {
        if ($height > $width * ($image->height / $image->width)) {
            return [$height * ($image->width / $image->height), $height];
        }

        return [$width, $width * ($image->height / $image->width)];
    }

    /**
     * Resolve the crop offset.
     * @param  Image $image The source image.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return array   The crop offset.
     */
    public function resolveCropOffset(Image $image, $width, $height)
    {
        list($offset_percentage_x, $offset_percentage_y) = $this->getCrop();

        $offset_x = (int)(($image->width * $offset_percentage_x / 100) - ($width / 2));
        $offset_y = (int)(($image->height * $offset_percentage_y / 100) - ($height / 2));

        $max_offset_x = $image->width - $width;
        $max_offset_y = $image->height - $height;

        if ($offset_x < 0) {
            $offset_x = 0;
        }

        if ($offset_y < 0) {
            $offset_y = 0;
        }

        if ($offset_x > $max_offset_x) {
            $offset_x = $max_offset_x;
        }

        if ($offset_y > $max_offset_y) {
            $offset_y = $max_offset_y;
        }

        return [$offset_x, $offset_y];
    }

    /**
     * Resolve crop.
     * @return integer[] The resolved crop.
     */
    public function getCrop()
    {
        $cropMethods = [
            'top-left' => [0, 0],
            't' => [50, 0],
            'top' => [50, 0],
            'top-right' => [100, 0],
            'l' => [0, 50],
            'left' => [0, 50],
            'center' => [50, 50],
            'r' => [0, 50],
            'right' => [100, 50],
            'bottom-left' => [0, 100],
            'b' => [50, 100],
            'bottom' => [50, 100],
            'bottom-right' => [100, 100],
        ];

        if (array_key_exists($this->a, $cropMethods)) {
            return $cropMethods[$this->a];
        }

        if (preg_match('/^crop-([\d]{1,3})-([\d]{1,3})*$/', $this->a, $matches)) {
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
     * @param  Image $image The source image.
     * @param  string $fit The fit.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return Image The manipulated image.
     */
    public function doResize(Image $image, $fit, $width, $height)
    {
        $inputWidth = $image->width;
        $inputHeight = $image->height;

        // Scaling calculations
        $xFactor = 1.0;
        $yFactor = 1.0;
        $targetResizeWidth = $width;
        $targetResizeHeight = $height;
        if ($width > 0 && $height > 0) {
            // Fixed width and height
            $xFactor = (double)($inputWidth / $width);
            $yFactor = (double)($inputHeight / $height);
            switch ($fit) {
                case 'square':
                case 'squaredown':
                case 'crop':
                    if ($xFactor < $yFactor) {
                        $targetResizeHeight = (int)round((double)($inputHeight / $xFactor));
                        $yFactor = $xFactor;
                    } else {
                        $targetResizeWidth = (int)round((double)($inputWidth / $yFactor));
                        $xFactor = $yFactor;
                    }
                    break;
                case 'letterbox':
                case 'fit':
                case 'fitup':
                    if ($xFactor > $yFactor) {
                        $targetResizeHeight = (int)round((double)($inputHeight / $xFactor));
                        $yFactor = $xFactor;
                    } else {
                        $targetResizeWidth = (int)round((double)($inputWidth / $yFactor));
                        $xFactor = $yFactor;
                    }
                    break;
            }
        } else {
            if ($width > 0) {
                // Fixed width
                $xFactor = (double)($inputWidth / $width);
                if ($fit == 'absolute') {
                    $targetResizeHeight = $height = $inputHeight;
                } else {
                    // Auto height
                    $yFactor = $xFactor;
                    $targetResizeHeight = $height = (int)round((double)($inputHeight / $yFactor));
                }
            } else {
                if ($height > 0) {
                    // Fixed height
                    $yFactor = (double)($inputHeight / $height);
                    if ($fit == 'absolute') {
                        $targetResizeWidth = $width = $inputWidth;
                    } else {
                        // Auto width
                        $xFactor = $yFactor;
                        $targetResizeWidth = $width = (int)round((double)($inputWidth / $xFactor));
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
        $xResidual = (double)($xShrink / $xFactor);
        $yResidual = (double)($yShrink / $yFactor);

        // Do not enlarge the output if the input width *or* height
        // are already less than the required dimensions
        if ($this->withoutEnlargement($fit)) {
            if ($inputWidth < $width || $inputHeight < $height) {
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


        if ($xShrink > 1 || $yShrink > 1) {
            if ($yShrink > 1) {
                $image = $image->shrinkv($yShrink);
            }
            if ($xShrink > 1) {
                $image = $image->shrinkh($xShrink);
            }
            // Recalculate residual float based on dimensions of required vs shrunk images
            $shrunkWidth = $image->width;
            $shrunkHeight = $image->height;
            $xResidual = (double)($targetResizeWidth / $shrunkWidth);
            $yResidual = (double)($targetResizeHeight / $shrunkHeight);
        }

        // Use affine increase or kernel reduce with the remaining float part
        $shouldAffineTransform = $xResidual != 1.0 || $yResidual != 1.0;

        if ($shouldAffineTransform) {
            // Perform kernel-based reduction
            if ($yResidual < 1.0 || $xResidual < 1.0) {
                if ($yResidual < 1.0) {
                    $image = $image->reducev(1.0 / $yResidual, ['kernel' => 'lanczos3']);
                }
                if ($xResidual < 1.0) {
                    $image = $image->reduceh(1.0 / $xResidual, ['kernel' => 'lanczos3']);
                }
            }
            // Perform affine enlargement
            if ($yResidual > 1.0 || $xResidual > 1.0) {
                if ($yResidual > 1.0) {
                    $image = $image->affine([1.0, 0.0, 0.0, $yResidual], ['interpolate' => 'bicubic']);
                }
                if ($xResidual > 1.0) {
                    $image = $image->affine([$xResidual, 0.0, 0.0, 1.0], ['interpolate' => 'bicubic']);
                }
            }
        }

        if ($image->width != $width || $image->height != $height) {
            if ($fit == 'letterbox') {
                if ($this->bg !== null) {
                    $backgroundColor = (new Color($this->bg))->formatted();
                } else {
                    $backgroundColor = [
                        0,
                        0,
                        0,
                        0
                    ];
                }

                // Scale up 8-bit values to match 16-bit input image
                $multiplier = Utils::is16Bit($image->interpretation) ? 256 : 1;

                // Create background colour
                if ($image->bands > 2) {
                    $background = [
                        $multiplier * $backgroundColor[0],
                        $multiplier * $backgroundColor[1],
                        $multiplier * $backgroundColor[2]
                    ];
                } else {
                    // Convert sRGB to greyscale
                    $background = [$multiplier * (0.2126 * $backgroundColor[0] + 0.7152 * $backgroundColor[1] + 0.0722 * $backgroundColor[2])];
                }

                $hasAlpha = Utils::hasAlpha($image);

                // Add alpha channel to background colour
                if ($backgroundColor[3] < 255 || $hasAlpha) {
                    array_push($background, $backgroundColor[3] * $multiplier);
                }

                // Add non-transparent alpha channel, if required
                if ($backgroundColor[3] < 255 && !$hasAlpha) {
                    $image = $image->bandjoin(vips_image_new_matrix($image->width,
                        $image->height))->new_from_image(255 * $multiplier);
                }

                $left = (int)round(($width - $image->width) / 2);
                $top = (int)round(($height - $image->height) / 2);
                $image = $image->embed($left, $top, $width, $height,
                    ['extend' => 'background', 'background' => $background]);
            } else {
                if (in_array($fit, ['square', 'squaredown', 'crop'], true)) {
                    list($offset_x, $offset_y) = $this->resolveCropOffset($image, $width, $height);
                    $width = min($image->width, $width);
                    $height = min($image->height, $height);

                    $image = $image->extract_area($offset_x, $offset_y, $width, $height);
                }
            }
        }

        return $image;
    }
}