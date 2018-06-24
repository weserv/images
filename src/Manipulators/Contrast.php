<?php

namespace Weserv\Images\Manipulators;

use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Image;

/**
 * @property string $con
 */
class Contrast extends BaseManipulator
{
    /**
     * Perform contrast image manipulation.
     *
     * @param Image $image The source image.
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if ($this->con === null) {
            return $image;
        }

        $contrast = $this->getContrast();

        if ($contrast !== 0) {
            // Map contrast from -100/100 to -30/30 range
            $contrast *= 0.3;

            $image = $this->sigmoid($image, $contrast);
        }

        return $image;
    }

    /**
     * *magick's sigmoidal non-linearity contrast control
     * equivalent in libvips
     *
     * This is a standard contrast adjustment technique: grey values are put through
     * an S-shaped curve which boosts the slope in the mid-tones and drops it for
     * white and black.
     *
     * This will apply to RGB. And takes no account of image gamma, and applies the
     * contrast boost to R, G and B bands, thereby also boosting colourfulness.
     *
     * @param Image $image The source image.
     * @param float $contrast Strength of the contrast (typically 3-20).
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     */
    public function sigmoid(Image $image, float $contrast): Image
    {
        // If true increase the contrast, if false decrease the contrast.
        $sharpen = $contrast > 0;

        // Midpoint of the contrast (typically 0.5).
        $midpoint = 0.5;

        $contrast = (float) abs($contrast);

        $ushort = $image->format === BandFormat::USHORT;

        /**
         * Make a identity LUT, that is, a lut where each pixel has the value of
         * its index ... if you map an image through the identity, you get the
         * same image back again.
         *
         * LUTs in libvips are just images with either the width or height set
         * to 1, and the 'interpretation' tag set to HISTOGRAM.
         *
         * If 'ushort' is TRUE, we make a 16-bit LUT, ie. 0 - 65535 values;
         * otherwise it's 8-bit (0 - 255)
         */
        $lut = Image::identity(['ushort' => $ushort]);

        // Rescale so each element is in [0, 1]
        $range = $lut->max();
        $lut = $lut->divide($range);

        /**
         * The sigmoidal equation, see
         *
         * https://www.imagemagick.org/Usage/color_mods/#sigmoidal
         *
         * and
         *
         * http://osdir.com/ml/video.image-magick.devel/2005-04/msg00006.html
         *
         * Though that's missing a term -- it should be
         *
         * (1/(1+exp(β*(α-u))) - 1/(1+exp(β*α))) /
         *   (1/(1+exp(β*(α-1))) - 1/(1+exp(β*α)))
         *
         * ie. there should be an extra α in the second term
         */
        if ($sharpen) {
            $x = $lut->multiply(-1)->add($midpoint)->multiply($contrast)->exp()->add(1)->pow(-1);
            $min = $x->min();
            $max = $x->max();
            $result = $x->subtract($min)->divide($max - $min);
        } else {
            $min = 1 / (1 + exp($contrast * $midpoint));
            $max = 1 / (1 + exp($contrast * ($midpoint - 1)));
            $x = $lut->multiply($max - $min)->add($min);
            $result = $x->multiply(-1)->add(1)->divide($x)->log()->divide($contrast)->multiply(-1)->add($midpoint);
        }

        // Rescale back to 0 - 255 or 0 - 65535
        $result = $result->multiply($range);

        /**
         * And get the format right ... $result will be a float image after all
         * that maths, but we want uchar or ushort
         */
        $result = $result->cast($ushort ? BandFormat::USHORT : BandFormat::UCHAR);

        return $image->maplut($result);
    }

    /**
     * Resolve contrast amount.
     *
     * @return int The resolved contrast amount.
     */
    public function getContrast(): int
    {
        if (!is_numeric($this->con)) {
            return 0;
        }

        if ($this->con < -100 || $this->con > 100) {
            return 0;
        }

        return (int)$this->con;
    }
}
