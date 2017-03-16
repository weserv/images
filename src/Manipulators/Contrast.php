<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;

/**
 * @property string $con
 */
class Contrast extends BaseManipulator
{
    /**
     * Perform contrast image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if (!$this->con) {
            return $image;
        }

        $contrast = $this->getContrast();

        if ($contrast !== 0) {
            $contrast = Utils::mapToRange($contrast, -100, 100, -30, 30);

            $image = $this->sigRGB($image, $contrast > 0, 0.5, abs($contrast));
            /*$image = $this->sigLAB($image, $contrast > 0, 0.5, abs($contrast));*/
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
     * @param bool $sharpen If true increase the contrast, if false decrease the contrast.
     * @param float $midpoint Midpoint of the contrast (typically 0.5).
     * @param float $contrast Strength of the contrast (typically 3-20).
     * @param bool $ushort Indicating if we have a 16-bit LUT.
     *
     * @return Image 16-bit or 8-bit LUT
     */
    public function sigmoid(bool $sharpen, float $midpoint, float $contrast, bool $ushort = false): Image
    {
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
         * http://www.imagemagick.org/Usage/color_mods/#sigmoidal
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
        return $result;
    }

    /**
     * Apply to RGB. This takes no account of image gamma, and applies the
     * contrast boost to R, G and B bands, thereby also boosting colourfulness.
     *
     * @param Image $image The source image.
     * @param bool $sharpen If true increase the contrast, if false decrease the contrast.
     * @param float $midpoint Midpoint of the contrast (typically 0.5).
     * @param float $contrast Strength of the contrast (typically 3-20).
     *
     * @return Image The manipulated image.
     */
    public function sigRGB(Image $image, bool $sharpen, float $midpoint, float $contrast): Image
    {
        $lut = $this->sigmoid($sharpen, $midpoint, $contrast, $image->format === BandFormat::USHORT);
        return $image->maplut($lut);
    }


    /**
     * Fancier: apply to L of CIELAB. This will change luminance equally, and will
     * not change colourfulness.
     *
     * @param Image $image The source image.
     * @param bool $sharpen If true increase the contrast, if false decrease the contrast.
     * @param float $midpoint Midpoint of the contrast (typically 0.5).
     * @param float $contrast Strength of the contrast (typically 3-20).
     *
     * @return Image The manipulated image.
     */
    public function sigLAB(Image $image, bool $sharpen, float $midpoint, float $contrast): Image
    {
        $oldInterpretation = $image->interpretation;

        /**
         * Labs is CIELAB with colour values expressed as short (signed 16-bit ints)
         * L is in 0 - 32767
         */
        $image = $image->colourspace(Interpretation::LABS);

        // Make a 16-bit LUT, then shrink by x2 to make it fit the range of L in labs
        $lut = $this->sigmoid($sharpen, $midpoint, $contrast, true);
        $lut = $lut->shrinkh(2)->multiply(0.5);
        $lut = $lut->cast(BandFormat::SHORT);

        /**
         * Get the L band from our labs image, map though the LUT, then reattach the
         * ab bands from the labs image
         */
        $L = $image->extract_band(0);
        $AB = $image->extract_band(1, ['n' => 2]);
        $L = $L->maplut($lut);
        $image = $L->bandjoin($AB);

        /**
         * And back to our original colourspace again (probably rgb)
         *
         * After the manipulation above, $image will just be tagged as a generic
         * multiband image, vips will no longer know that it's a labs, so we need to
         * tell colourspace what the source space is
         */
        return $image->colourspace($oldInterpretation, ['source_space' => Interpretation::LABS]);
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
