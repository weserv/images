<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $bg
 * @property bool $hasAlpha
 * @property bool $is16Bit
 * @property int $maxAlpha
 */
class Background extends BaseManipulator
{


    /**
     * Perform background image manipulation.
     *
     * @param Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if ($this->bg === null) {
            return $image;
        }

        $backgroundColor = (new Color($this->bg))->formatted();

        if ($backgroundColor && $this->hasAlpha) {
            $maxAlpha = $this->maxAlpha;
            $is16Bit = $this->is16Bit;

            // Ensures that the image alpha channel is premultiplied before doing any background color transformations
            // to avoid dark fringing around bright pixels
            // See: http://entropymine.com/imageworsener/resizealpha/
            $image = Utils::premultiplyImage($image, $maxAlpha);

            // Scale up 8-bit values to match 16-bit input image
            $multiplier = $is16Bit ? 256 : 1;

            // TODO Wow dirty hack. Find a alternative for background creation.
            $background = $image->more(255)->ifthenelse(255, [
                $multiplier * $backgroundColor[0],
                $multiplier * $backgroundColor[1],
                $multiplier * $backgroundColor[2],
                $multiplier * $backgroundColor[3],
            ]);

            // Ensure overlay is premultiplied sRGB
            $background = $background->colourspace(Utils::VIPS_INTERPRETATION_sRGB)->premultiply();

            // Split src into non-alpha and alpha channels
            $srcWithoutAlpha = $image->extract_band(0, ['n' => $image->bands - 1]);
            $srcAlpha = $image->extract_band($image->bands - 1, ['n' => 1])->multiply(1.0 / 255.0);

            // Split dst into non-alpha and alpha channels
            $dstWithoutAlpha = $background->extract_band(0, ['n' => $background->bands - 1]);
            $dstAlpha = $background->extract_band($background->bands - 1, ['n' => 1])->multiply(1.0 / 255.0);

            /**
             * Compute normalized output alpha channel:
             *
             * References:
             * - http://en.wikipedia.org/wiki/Alpha_compositing#Alpha_blending
             * - https://github.com/jcupitt/ruby-vips/issues/28#issuecomment-9014826
             *
             * out_a = src_a + dst_a * (1 - src_a)
             *                         ^^^^^^^^^^^
             *                            t0
             */
            $t0 = $srcAlpha->linear(-1.0, 1.0);
            $outAlphaNormalized = $srcAlpha->add($dstAlpha->multiply($t0));

            /**
             * Compute output RGB channels:
             *
             * Wikipedia:
             * out_rgb = (src_rgb * src_a + dst_rgb * dst_a * (1 - src_a)) / out_a
             *                                                ^^^^^^^^^^^
             *                                                    t0
             *
             * Omit division by `out_a` since `Compose` is supposed to output a
             * premultiplied RGBA image as reversal of premultiplication is handled
             * externally
             */
            $outRGBPremultiplied = $srcWithoutAlpha->add($dstWithoutAlpha->multiply($t0));

            // Combine RGB and alpha channel into output image:
            $image = $outRGBPremultiplied->bandjoin($outAlphaNormalized->multiply(255.0));
        }

        return $image;
    }
}
