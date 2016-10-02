<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

use Jcupitt\Vips\Image;

class Utils
{
    const EXIF_IFD0_ORIENTATION = "exif-ifd0-Orientation";

    const VIPS_INTERPRETATION_B_W = "b-w";
    const VIPS_INTERPRETATION_CMYK = "cmyk";
    const VIPS_INTERPRETATION_RGB = "rgb";
    const VIPS_INTERPRETATION_sRGB = "srgb";
    const VIPS_INTERPRETATION_RGB16 = "rgb16";
    const VIPS_INTERPRETATION_GREY16 = "grey16";

    const VIPS_ANGLE_D0 = "d0";
    const VIPS_ANGLE_D90 = "d90";
    const VIPS_ANGLE_D180 = "d180";
    const VIPS_ANGLE_D270 = "d270";

    /**
     * Are pixel values in this image 16-bit integer?
     *
     * @param  string $interpretation The VipsInterpretation
     * @return bool indicating if the pixel values in this image are 16-bit
     */
    public static function is16Bit($interpretation)
    {
        return $interpretation == self::VIPS_INTERPRETATION_RGB16 || $interpretation == self::VIPS_INTERPRETATION_GREY16;
    }

    /**
     * Return the image alpha maximum. Useful for combining alpha bands. scRGB
     * images are 0 - 1 for image data, but the alpha is 0 - 255.
     *
     * @param  string $interpretation The VipsInterpretation
     * @return int the image alpha maximum
     */
    public static function maximumImageAlpha($interpretation)
    {
        return self::is16Bit($interpretation) ? 65535 : 255;
    }

    /**
     * Does this image have an alpha channel?
     * Uses colour space interpretation with number of channels to guess this.
     *
     * @param  Image $image The source image.
     * @return bool indicating if this image has an alpha channel.
     */
    public static function hasAlpha($image)
    {
        $bands = $image->bands;
        $interpretation = $image->interpretation;

        return ($bands == 2 && $interpretation == self::VIPS_INTERPRETATION_B_W) || ($bands == 4 && $interpretation != self::VIPS_INTERPRETATION_CMYK) || ($bands == 5 && $interpretation == self::VIPS_INTERPRETATION_CMYK);
    }

    /**
     * Get EXIF Orientation of image, if any.
     *
     * @param  Image $image The source image.
     * @return bool indicating if this image has an alpha channel.
     */
    public static function exifOrientation($image)
    {
        $orientation = 0;
        // FIXME: vips_call(): VipsOperation: class 'get_typeof' not found
        /*if ($image->get_typeof(self::EXIF_IFD0_ORIENTATION) != 0) {
            $exif = $image->get_string(self::EXIF_IFD0_ORIENTATION);
            if ($exif !== null) {
                $orientation = (int)$exif[0];
            }
        }*/
        // FIXME: Also not working:
        /*vips_image_get_typeof($image, self::EXIF_IFD0_ORIENTATION)*/
        return $orientation;
    }

    /**
     * Set EXIF Orientation of image.
     *
     * @param Image $image The source image.
     * $param integer $orientation EXIF Orientation.
     */
    public static function setExifOrientation($image, $orientation)
    {
         /*$exif = [$orientation, $orientation, $orientation];
         // FIXME: vips_call(): VipsOperation: class 'set' not found
         $image->set(self::EXIF_IFD0_ORIENTATION, $exif);*/
         // FIXME: Also not working:
         /*vips_image_set_string($image, self::EXIF_IFD0_ORIENTATION, (string) $orientation);*/
    }

    /**
     * Remove EXIF Orientation from image.
     *
     * @param Image $image The source image.
     */
    public static function removeExifOrientation($image)
    {
        self::setExifOrientation($image, 0);
    }

    /**
     * Calculate the angle of rotation and need-to-flip for the output image.
     * In order of priority:
     *  1. Use explicitly requested angle (supports 90, 180, 270)
     *  2. Use input image EXIF Orientation header - supports mirroring
     *  3. Otherwise default to zero, i.e. no rotation
     *
     * @param  integer $angle explicitly requested angle
     * @param  Image $image The source image.
     * @return array [rotation, flip, flop]
     */
    public static function calculateRotationAndFlip($angle, $image)
    {
        $rotate = self::VIPS_ANGLE_D0;
        $flip = false;
        $flop = false;
        if ($angle == -1) {
            switch (self::exifOrientation($image)) {
                case 6:
                    $rotate = self::VIPS_ANGLE_D90;
                    break;
                case 3:
                    $rotate = self::VIPS_ANGLE_D180;
                    break;
                case 8:
                    $rotate = self::VIPS_ANGLE_D270;
                    break;
                case 2: // flop 1
                    $flop = true;
                    break;
                case 7: // flip 6
                    $flip = true;
                    $rotate = self::VIPS_ANGLE_D90;
                    break;
                case 4: // flop 3
                    $flop = true;
                    $rotate = self::VIPS_ANGLE_D180;
                    break;
                case 5: // flip 8
                    $flip = true;
                    $rotate = self::VIPS_ANGLE_D270;
                    break;
            }
        } else {
            if ($angle == 90) {
                $rotate = self::VIPS_ANGLE_D90;
            } else {
                if ($angle == 180) {
                    $rotate = self::VIPS_ANGLE_D180;
                } else {
                    if ($angle == 270) {
                        $rotate = self::VIPS_ANGLE_D270;
                    }
                }
            }
        }
        return [$rotate, $flip, $flop];
    }


}
