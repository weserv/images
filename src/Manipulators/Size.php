<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Exception\ImageTooLargeException;
use Intervention\Image\Image;

/**
 * @property string $dpr
 * @property string $fit
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
        $dpr = $this->getDpr();

        list($width, $height) = $this->resolveMissingDimensions($image, $width, $height);

        // Check if image size is greater then the maximum allowed image size after dimension is resolved
        $this->checkImageSize($width, $height);

        list($width, $height) = $this->applyDpr($width, $height, $dpr);
        /* list($width, $height) = $this->limitImageSize($width, $height); */

        if ((int)$width !== (int)$image->width() or (int)$height !== (int)$image->height()) {
            $image = $this->runResize($image, $fit, (int)$width, (int)$height);
        }

        return $image;
    }

    /**
     * Resolve width.
     * @return integer|null The resolved width.
     */
    public function getWidth()
    {
        if (!is_numeric($this->w)) {
            return;
        }

        if ($this->w <= 0) {
            return;
        }

        return (int)$this->w;
    }

    /**
     * Resolve height.
     * @return integer|null The resolved height.
     */
    public function getHeight()
    {
        if (!is_numeric($this->h)) {
            return;
        }

        if ($this->h <= 0) {
            return;
        }

        return (int)$this->h;
    }

    /**
     * Resolve fit.
     * @return string The resolved fit.
     */
    public function getFit()
    {
        // Deprecated: 'fit', 'fitup', 'square', 'squaredown', 'absolute'
        if (in_array($this->t, [
            'contain',
            'fill',
            'max',
            'stretch',
            'fit',  // Deprecated use contain instead
            'fitup', // Deprecated use fill instead
            'square', // Deprecated use crop instead
            'squaredown',  // Deprecated use max instead
            'absolute' // Deprecated use stretch instead
        ], true)) {
            return $this->t;
        }

        if (preg_match('/^(crop)(-top-left|-top|-top-right|-left|-center|-right|-bottom-left|-bottom|-bottom-right|-[\d]{1,3}-[\d]{1,3})*$/',
            $this->t)) {
            return 'crop';
        }

        return 'contain';
    }

    /**
     * Resolve the device pixel ratio.
     * @return double The device pixel ratio.
     */
    public function getDpr()
    {
        if (!is_numeric($this->dpr)) {
            return 1.0;
        }

        if ($this->dpr < 0 or $this->dpr > 8) {
            return 1.0;
        }

        return (double)$this->dpr;
    }

    /**
     * Resolve missing image dimensions.
     * @param  Image $image The source image.
     * @param  integer|null $width The image width.
     * @param  integer|null $height The image height.
     * @return integer[]    The resolved width and height.
     */
    public function resolveMissingDimensions(Image $image, $width, $height)
    {
        if (is_null($width) and is_null($height)) {
            $width = $image->width();
            $height = $image->height();
        }

        if (is_null($width)) {
            $width = $height * ($image->width() / $image->height());
        }

        if (is_null($height)) {
            $height = $width / ($image->width() / $image->height());
        }

        return [
            (int)$width,
            (int)$height,
        ];
    }

    /**
     * Check if image size is greater then the maximum allowed image size.
     * @param  double $width The image width.
     * @param  double $height The image height.
     * @return \double[] The limited width and height.
     * @throws \Exception if the provided image is too large for processing.
     */
    public function checkImageSize($width, $height)
    {
        if ($this->maxImageSize !== null) {
            $imageSize = $width * $height;

            if ($imageSize > $this->maxImageSize) {
                throw new ImageTooLargeException('Image is too large for processing. Width x Height should be less than 70 megapixels.');
                /*$width = $width / sqrt($imageSize / $this->maxImageSize);
                $height = $height / sqrt($imageSize / $this->maxImageSize);*/
            }
        }
        /*return [
            (double)$width,
            (double)$height,
        ];*/
    }

    /**
     * Apply the device pixel ratio.
     * @param  integer $width The target image width.
     * @param  integer $height The target image height.
     * @param  integer $dpr The device pixel ratio.
     * @return integer[] The modified width and height.
     */
    public function applyDpr($width, $height, $dpr)
    {
        $width = $width * $dpr;
        $height = $height * $dpr;

        return [
            (int)$width,
            (int)$height,
        ];
    }

    /**
     * Perform resize image manipulation.
     * @param  Image $image The source image.
     * @param  string $fit The fit.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return Image   The manipulated image.
     */
    public function runResize(Image $image, $fit, $width, $height)
    {
        if ($fit === 'contain' || $fit === 'fit' || $fit === 'fitup') {
            return $this->runContainResize($image, $width, $height);
        }

        if ($fit === 'fill') {
            return $this->runFillResize($image, $width, $height);
        }

        if ($fit === 'max') {
            return $this->runMaxResize($image, $width, $height);
        }

        if ($fit === 'stretch' || $fit === 'absolute') {
            return $this->runStretchResize($image, $width, $height);
        }

        if ($fit === 'crop' || $fit === 'square' || $fit === 'squaredown') {
            return $this->runCropResize($image, $width, $height);
        }

        return $image;
    }

    /**
     * Perform contain resize image manipulation.
     * @param  Image $image The source image.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return Image   The manipulated image.
     */
    public function runContainResize(Image $image, $width, $height)
    {
        return $image->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
        });
    }

    /**
     * Perform fill resize image manipulation.
     * @param  Image $image The source image.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return Image   The manipulated image.
     */
    public function runFillResize($image, $width, $height)
    {
        $image = $this->runMaxResize($image, $width, $height);

        return $image->resizeCanvas($width, $height, 'center');
    }

    /**
     * Perform max resize image manipulation.
     * @param  Image $image The source image.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return Image   The manipulated image.
     */
    public function runMaxResize(Image $image, $width, $height)
    {
        return $image->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
    }

    /**
     * Perform stretch resize image manipulation.
     * @param  Image $image The source image.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return Image   The manipulated image.
     */
    public function runStretchResize(Image $image, $width, $height)
    {
        return $image->resize($width, $height);
    }

    /**
     * Perform crop resize image manipulation.
     * @param  Image $image The source image.
     * @param  integer $width The width.
     * @param  integer $height The height.
     * @return Image   The manipulated image.
     */
    public function runCropResize(Image $image, $width, $height)
    {
        list($resize_width, $resize_height) = $this->resolveCropResizeDimensions($image, $width, $height);

        $image->resize($resize_width, $resize_height, function ($constraint) {
            $constraint->aspectRatio();
        });

        list($offset_x, $offset_y) = $this->resolveCropOffset($image, $width, $height);

        return $image->crop($width, $height, $offset_x, $offset_y);
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
        if ($height > $width * ($image->height() / $image->width())) {
            return [$height * ($image->width() / $image->height()), $height];
        }

        return [$width, $width * ($image->height() / $image->width())];
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

        $offset_x = (int)(($image->width() * $offset_percentage_x / 100) - ($width / 2));
        $offset_y = (int)(($image->height() * $offset_percentage_y / 100) - ($height / 2));

        $max_offset_x = $image->width() - $width;
        $max_offset_y = $image->height() - $height;

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

        if (array_key_exists($this->a, $cropMethods)) {
            return $cropMethods[$this->a];
        }

        if (preg_match('/^crop-([\d]{1,3})-([\d]{1,3})*$/', $this->a, $matches)) {
            if ($matches[1] > 100 or $matches[2] > 100) {
                return [50, 50];
            }

            return [
                (int)$matches[1],
                (int)$matches[2],
            ];
        }

        return [50, 50];
    }
}