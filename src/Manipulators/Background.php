<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use AndriesLouw\imagesweserv\Manipulators\Helpers\Color;
use AndriesLouw\imagesweserv\Manipulators\Helpers\Utils;
use Jcupitt\Vips\Image;

/**
 * @property string $bg
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
        if ($this->bg == null) {
            return $image;
        }

        $backgroundColor = (new Color($this->bg))->formatted();

        if ($backgroundColor && Utils::hasAlpha($image)) {
            $interpretation = $image->interpretation;

            // Scale up 8-bit values to match 16-bit input image
            $multiplier = Utils::is16Bit($interpretation) ? 256 : 1;

            // TODO flatten is not the right option because it accepts no alpha channel
            // We only want to change the alpha channel to another color. (bandjoin?)
            /*$pixel = Image::black(1, 1)->add([
                $backgroundColor[0] * $multiplier,
                $backgroundColor[1] * $multiplier,
                $backgroundColor[2] * $multiplier,
                $backgroundColor[3] * $multiplier
            ])->cast($image->format);

            $background = $pixel->embed(0, 0, $image->width, $image->height, ["extend" => "copy"]);
            $background->interpretation = $image->interpretation;

            $image = $image->bandjoin([$background]);

            $image->ifthenelse(255, [204, 204, 204, 127.5], ['blend' => true]);*/

            // Background colour
            $background = [
                $backgroundColor[0] * $multiplier,
                $backgroundColor[1] * $multiplier,
                $backgroundColor[2] * $multiplier
            ];
            $image = $image->flatten(
                [
                "background" => $background,
                "max_alpha" => Utils::maximumImageAlpha($interpretation)
                ]
            );
        }

        return $image;
    }
}
