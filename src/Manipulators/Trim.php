<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Intervention\Image\Image;

/**
 * @property string $trim
 */
class Trim extends BaseManipulator
{
    /**
     * Perform trim image manipulation.
     * @param  Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $driverName = $image->getDriver()->getDriverName();
        $trim = $this->getTrim($driverName);


        if ($trim) {
            if ($driverName == 'Gd') {
                // Sensitivity between 0 and 255 if using GD driver
                $width = $image->getWidth();
                $height = $image->getHeight();

                $gdImage = $image->getCore();

                if ($box = $this->imageTrimmedBox($width, $height, $gdImage, $trim)) {
                    $gdTrimmed = imagecreatetruecolor($box['w'], $box['h']);
                    imagecopy($gdTrimmed, $gdImage, 0, 0, $box['l'], $box['t'], $box['w'], $box['h']);

                    $image->setCore($gdTrimmed);
                }
            } else {
                // Percentaged tolerance level between 0 and 100 if using Imagick driver
                $image->trim('top-left', null, $trim);
            }
        }
        return $image;
    }

    /**
     * Resolve trim amount.
     * @param $driverName string Driver name (Gd or Imagick)
     * @return string The resolved gamma amount.
     */
    public function getTrim($driverName)
    {
        if ($this->trim === '') {
            return 10;
        }

        if (!is_numeric($this->trim)) {
            return;
        }

        if ($this->trim < 0 or ($driverName == 'Gd' && $this->trim > 255) or ($driverName == 'Imagick' && $this->trim > 100)) {
            return;
        }

        return (int)$this->trim;
    }

    public function imageTrimmedBox($cur_width, $cur_height, $gdImage, $t, $hex = null)
    {
        if ($hex == null) {
            $hex = imagecolorat($gdImage, 2, 2);
        } // 2 pixels in to avoid messy edges
        $r = ($hex >> 16) & 0xFF;
        $g = ($hex >> 8) & 0xFF;
        $b = $hex & 0xFF;
        $c = round(($r + $g + $b) / 3); // average of rgb is good enough for a default

        $width = $cur_width;
        $height = $cur_height;
        $b_top = 0;
        $b_lft = 0;
        $b_btm = $height - 1;
        $b_rt = $width - 1;

        //top
        for (; $b_top < $height; ++$b_top) {
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($gdImage, $x, $b_top);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if (
                    ($r < $c - $t || $r > $c + $t) && // red not within tolerance of trim colour
                    ($g < $c - $t || $g > $c + $t) && // green not within tolerance of trim colour
                    ($b < $c - $t || $b > $c + $t) // blue not within tolerance of trim colour
                ) {
                    break 2;
                }
            }
        }

        // return false when all pixels are trimmed
        if ($b_top == $height) {
            return false;
        }

        // bottom
        for (; $b_btm >= 0; --$b_btm) {
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($gdImage, $x, $b_btm);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if (
                    ($r < $c - $t || $r > $c + $t) && // red not within tolerance of trim colour
                    ($g < $c - $t || $g > $c + $t) && // green not within tolerance of trim colour
                    ($b < $c - $t || $b > $c + $t) // blue not within tolerance of trim colour
                ) {
                    break 2;
                }
            }
        }

        // left
        for (; $b_lft < $width; ++$b_lft) {
            for ($y = $b_top; $y <= $b_btm; ++$y) {
                $rgb = imagecolorat($gdImage, $b_lft, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if (
                    ($r < $c - $t || $r > $c + $t) && // red not within tolerance of trim colour
                    ($g < $c - $t || $g > $c + $t) && // green not within tolerance of trim colour
                    ($b < $c - $t || $b > $c + $t) // blue not within tolerance of trim colour
                ) {
                    break 2;
                }
            }
        }

        // right
        for (; $b_rt >= 0; --$b_rt) {
            for ($y = $b_top; $y <= $b_btm; ++$y) {
                $rgb = imagecolorat($gdImage, $b_rt, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if (
                    ($r < $c - $t || $r > $c + $t) && // red not within tolerance of trim colour
                    ($g < $c - $t || $g > $c + $t) && // green not within tolerance of trim colour
                    ($b < $c - $t || $b > $c + $t) // blue not within tolerance of trim colour
                ) {
                    break 2;
                }
            }
        }

        $b_btm++;
        $b_rt++;
        return array(
            'l' => $b_lft,
            't' => $b_top,
            'r' => $b_rt,
            'b' => $b_btm,
            'w' => $b_rt - $b_lft,
            'h' => $b_btm - $b_top
        );
    }
}
