<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use Jcupitt\Vips\Extend;
use Jcupitt\Vips\Image;

/**
 * @property string $bg
 * @property bool $hasAlpha
 * @property bool $is16Bit
 * @property int $maxAlpha
 * @property bool $isPremultiplied
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
        if ($this->bg === null || !$this->hasAlpha) {
            return $image;
        }

        $background = new Color($this->bg);

        if ($background->isTransparent()) {
            return $image;
        }

        $backgroundRGBA = $background->toRGBA();

        $maxAlpha = $this->maxAlpha;
        $is16Bit = $this->is16Bit;

        // Scale up 8-bit values to match 16-bit input image
        $multiplier = $is16Bit ? 256 : 1;

        if ($image->bands < 3 || !$background->hasAlphaChannel()) {
            // If it's a 8bit-alpha channel image or the requested background color hasn't an alpha channel;
            // then flatten the alpha out of an image, replacing it with a constant background color.

            if ($image->bands < 3) {
                // Convert sRGB to greyscale
                $backgroundColor = $multiplier * (
                        (0.2126 * $backgroundRGBA[0]) +
                        (0.7152 * $backgroundRGBA[1]) +
                        (0.0722 * $backgroundRGBA[2]));
            } else {
                $backgroundColor = [
                    $multiplier * $backgroundRGBA[0],
                    $multiplier * $backgroundRGBA[1],
                    $multiplier * $backgroundRGBA[2]
                ];
            }

            $image = $image->flatten([
                'background' => $backgroundColor,
                'max_alpha' => $maxAlpha
            ]);
        } else {
            // If the image has more than two bands and the requested background color has an alpha channel;
            // alpha compositing.

            // Make a 1x1 pixel with the red channel and cast it to the origin image numerical format.
            $pixel = Image::black(1, 1)->add($multiplier * $backgroundRGBA[0])->cast($image->format);

            // Extend this 1x1 pixel to match the origin image dimensions.
            $backgroundImage = $pixel->embed(0, 0, $image->width, $image->height, ['extend' => Extend::COPY]);

            // Ensure that the interpretation of the background is the same as the origin image.
            $backgroundImage->interpretation = $image->interpretation;

            // Bandwise join the rest of the channels including the alpha channel.
            $backgroundImage = $backgroundImage->bandjoin(
                [
                    $multiplier * $backgroundRGBA[1],
                    $multiplier * $backgroundRGBA[2],
                    $multiplier * $backgroundRGBA[3]
                ]
            );

            // Ensure overlay is premultiplied sRGB
            $backgroundImage = $backgroundImage->premultiply();

            // Ensures that the image alpha channel is premultiplied before doing any background color
            // transformations to avoid dark fringing around bright pixels
            // See: http://entropymine.com/imageworsener/resizealpha/
            if (!$this->isPremultiplied) {
                // Ensures that the image alpha channel is premultiplied before doing any affine transformations
                // to avoid dark fringing around bright pixels
                // See: http://entropymine.com/imageworsener/resizealpha/
                $image = $image->premultiply(['max_alpha' => $this->maxAlpha]);
                $this->isPremultiplied = true;
            }

            $image = $this->composite($image, $backgroundImage);
        }


        return $image;
    }

    /**
     * Alpha composite src over dst
     * Assumes alpha channels are already premultiplied and will be unpremultiplied after.
     *
     * @param  Image $src The source image.
     * @param  Image $dst The distance image (in this case the background).
     *
     * @return Image The manipulated image.
     */
    public function composite(Image $src, Image $dst): Image
    {
        // Split src into non-alpha and alpha channels
        $srcWithoutAlpha = $src->extract_band(0, ['n' => $src->bands - 1]);
        $srcAlpha = $src->extract_band($src->bands - 1, ['n' => 1])->multiply(1.0 / 255.0);

        // Split dst into non-alpha and alpha channels
        $dstWithoutAlpha = $dst->extract_band(0, ['n' => $dst->bands - 1]);
        $dstAlpha = $dst->extract_band($dst->bands - 1, ['n' => 1])->multiply(1.0 / 255.0);

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
        return $outRGBPremultiplied->bandjoin($outAlphaNormalized->multiply(255.0));
    }
}