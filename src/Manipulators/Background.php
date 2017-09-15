<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use Jcupitt\Vips\BandFormat;
use Jcupitt\Vips\Image;

/**
 * @property string $bg
 * @property string $t
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
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function run(Image $image): Image
    {
        // Skip this manipulator if:
        // - There's no bg parameter.
        // - Letterboxing is required.
        // - The image doesn't have an alpha channel.
        if (!$this->bg || $this->t === 'letterbox' || !$image->hasAlpha()) {
            return $image;
        }

        $background = new Color($this->bg);

        if ($background->isTransparent()) {
            return $image;
        }

        $backgroundRGBA = $background->toRGBA();

        if ($image->bands > 2 && $background->hasAlphaChannel()) {
            // If the image has more than two bands and the requested background color has an alpha channel;
            // alpha compositing.

            // Create a new image from a constant that matches the origin image dimensions
            $backgroundImage = $image->newFromImage($backgroundRGBA[0]);

            // Bandwise join the rest of the channels including the alpha channel.
            $backgroundImage = $backgroundImage->bandjoin(
                [
                    $backgroundRGBA[1],
                    $backgroundRGBA[2],
                    $backgroundRGBA[3]
                ]
            );

            // Ensure overlay is premultiplied sRGB
            $backgroundImage = $backgroundImage->premultiply();

            // Premultiply image alpha channel before background transformation
            if (!$this->isPremultiplied) {
                $image = $image->premultiply();
                $this->isPremultiplied = true;
            }

            $image = $this->composite($image, $backgroundImage);
        } else {
            // If it's a 8bit-alpha channel image or the requested background color hasn't an alpha channel;
            // then flatten the alpha out of an image, replacing it with a constant background color.
            $backgroundColor = [
                $backgroundRGBA[0],
                $backgroundRGBA[1],
                $backgroundRGBA[2]
            ];

            if ($image->bands < 3) {
                // Convert sRGB to greyscale
                $backgroundColor = (0.2126 * $backgroundRGBA[0]) +
                    (0.7152 * $backgroundRGBA[1]) +
                    (0.0722 * $backgroundRGBA[2]);
            }

            // Flatten on premultiplied images causes weird results
            // so unpremultiply if we have a premultiplied image.
            if ($this->isPremultiplied) {
                // Unpremultiply image alpha and cast pixel values to integer
                $image = $image->unpremultiply()->cast(BandFormat::UCHAR);

                $this->isPremultiplied = false;
            }

            $image = $image->flatten([
                'background' => $backgroundColor
            ]);
        }


        return $image;
    }

    /**
     * Alpha composite src over dst
     * Assumes alpha channels are already premultiplied and will be unpremultiplied after.
     *
     * @param Image $src The source image.
     * @param Image $dst The distance image (in this case the background).
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
