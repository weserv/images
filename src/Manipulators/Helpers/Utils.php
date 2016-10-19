<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

use Jcupitt\Vips\Enum\Interpretation;
use Jcupitt\Vips\Image;

class Utils
{
    const VIPS_META_ORIENTATION = 'orientation';

    const VIPS_INTERPRETATION_ERROR = -1;
    const VIPS_INTERPRETATION_MULTIBAND = 0;
    const VIPS_INTERPRETATION_B_W = 1;
    const VIPS_INTERPRETATION_HISTOGRAM = 10;
    const VIPS_INTERPRETATION_XYZ = 12;
    const VIPS_INTERPRETATION_LAB = 13;
    const VIPS_INTERPRETATION_CMYK = 15;
    const VIPS_INTERPRETATION_LABQ = 16;
    const VIPS_INTERPRETATION_RGB = 17;
    const VIPS_INTERPRETATION_CMC = 18;
    const VIPS_INTERPRETATION_LCH = 19;
    const VIPS_INTERPRETATION_LABS = 21;
    const VIPS_INTERPRETATION_SRGB = 22;
    const VIPS_INTERPRETATION_YXY = 23;
    const VIPS_INTERPRETATION_FOURIER = 24;
    const VIPS_INTERPRETATION_RGB16 = 25;
    const VIPS_INTERPRETATION_GREY16 = 26;
    const VIPS_INTERPRETATION_MATRIX = 27;
    const VIPS_INTERPRETATION_SCRGB = 28;
    const VIPS_INTERPRETATION_HSV = 29;

    const VIPS_INTERPRETATION_TO_COLOURSPACE = [
        self::VIPS_INTERPRETATION_ERROR => Interpretation::ERROR,
        self::VIPS_INTERPRETATION_MULTIBAND => Interpretation::MULTIBAND,
        self::VIPS_INTERPRETATION_B_W => Interpretation::B_W,
        self::VIPS_INTERPRETATION_HISTOGRAM => Interpretation::HISTOGRAM,
        self::VIPS_INTERPRETATION_XYZ => Interpretation::XYZ,
        self::VIPS_INTERPRETATION_LAB => Interpretation::LAB,
        self::VIPS_INTERPRETATION_CMYK => Interpretation::CMYK,
        self::VIPS_INTERPRETATION_LABQ => Interpretation::LABQ,
        self::VIPS_INTERPRETATION_RGB => Interpretation::RGB,
        self::VIPS_INTERPRETATION_CMC => Interpretation::CMC,
        self::VIPS_INTERPRETATION_LCH => Interpretation::LCH,
        self::VIPS_INTERPRETATION_LABS => Interpretation::LABS,
        self::VIPS_INTERPRETATION_SRGB => Interpretation::SRGB,
        self::VIPS_INTERPRETATION_YXY => Interpretation::YXY,
        self::VIPS_INTERPRETATION_FOURIER => Interpretation::FOURIER,
        self::VIPS_INTERPRETATION_RGB16 => Interpretation::RGB16,
        self::VIPS_INTERPRETATION_GREY16 => Interpretation::GREY16,
        self::VIPS_INTERPRETATION_MATRIX => Interpretation::MATRIX,
        self::VIPS_INTERPRETATION_SCRGB => Interpretation::SCRGB,
        self::VIPS_INTERPRETATION_HSV => Interpretation::HSV
    ];

    /**
     * Are pixel values in this image 16-bit integer?
     *
     * @param  int $interpretation The VipsInterpretation
     *
     * @return bool indicating if the pixel values in this image are 16-bit
     */
    public static function is16Bit(int $interpretation): bool
    {
        return $interpretation == self::VIPS_INTERPRETATION_RGB16
        || $interpretation == self::VIPS_INTERPRETATION_GREY16;
    }

    /**
     * Return the image alpha maximum. Useful for combining alpha bands. scRGB
     * images are 0 - 1 for image data, but the alpha is 0 - 255.
     *
     * @param  int $interpretation The VipsInterpretation
     *
     * @return int the image alpha maximum
     */
    public static function maximumImageAlpha(int $interpretation): int
    {
        return self::is16Bit($interpretation) ? 65535 : 255;
    }

    /**
     * Does this image have an alpha channel?
     * Uses colour space interpretation with number of channels to guess this.
     *
     * @param  Image $image The source image.
     *
     * @return bool indicating if this image has an alpha channel.
     */
    public static function hasAlpha(Image $image): bool
    {
        $bands = $image->bands;
        $interpretation = $image->interpretation;

        return ($bands == 2 && $interpretation == self::VIPS_INTERPRETATION_B_W)
        || ($bands == 4 && $interpretation != self::VIPS_INTERPRETATION_CMYK)
        || ($bands == 5 && $interpretation == self::VIPS_INTERPRETATION_CMYK);
    }

    /**
     * Get EXIF Orientation of image, if any.
     *
     * @param Image $image The source image.
     *
     * @return int EXIF Orientation
     */
    public static function exifOrientation(Image $image): int
    {
        if ($image->typeof(self::VIPS_META_ORIENTATION) !== 0) {
            $exif = $image->get(self::VIPS_META_ORIENTATION);
            return $exif;
        }
        return 0;
    }

    /**
     * Calculate the angle of rotation and need-to-flip for the output image.
     * Removes the EXIF orientation header if the image contains one.
     *
     * In order of priority:
     *  1. Check the rotation and mirroring of the EXIF orientation header
     *     and init the $rotate variable, $flip and the $flop variable.
     *  2. Removes the EXIF orientation header if the image contains one.
     *  3. Add explicitly requested angle (supports 90, 180, 270) to the $rotate variable
     *     (e.g. if the image is already rotated 90 degrees due to EXIF orientation header and
     *     the user wants also to rotate 90 degrees then we need to rotate 180 degrees).
     *  4. If the rotation is 360 degrees then add no rotation.
     *  5. Subtract 360 degrees if the rotation is higher than 270
     *     (this ensures that we have a valid rotation).
     *  6. If there's no EXIF orientation header and no explicitly requested angle
     *     then default the $rotate variable to zero, i.e. no rotation.
     *
     * @param  int $angle explicitly requested angle
     * @param  Image $image The source image.
     *
     * @return array [rotation, flip, flop]
     */
    public static function calculateRotationAndFlip(int $angle, Image $image): array
    {
        $rotate = 0;
        $flip = false;
        $flop = false;

        $exifOrientation = self::exifOrientation($image);

        // First auto-rotate the image if the image has the EXIF orientation header.
        // We could use `$image->autorot();` if it's supports the the various mirror modes.
        // Currently it doesn't support that so we need to check it by our self.
        switch ($exifOrientation) {
            case 6:
                $rotate = 90;
                break;
            case 3:
                $rotate = 180;
                break;
            case 8:
                $rotate = 270;
                break;
            case 2: // flop 1
                $flop = true;
                break;
            case 7: // flip 6
                $flip = true;
                $rotate = 90;
                break;
            case 4: // flop 3
                $flop = true;
                $rotate = 180;
                break;
            case 5: // flip 8
                $flip = true;
                $rotate = 270;
                break;
        }

        // Remove EXIF Orientation from image, if required.
        if ($exifOrientation !== 0) {
            $image->remove(self::VIPS_META_ORIENTATION);
        }

        // Add explicitly requested angle (supports 90, 180, 270) to the $rotate variable.
        if ($angle == 90) {
            $rotate += 90;
        } else {
            if ($angle == 180) {
                $rotate += 180;
            } else {
                if ($angle == 270) {
                    $rotate += 270;
                }
            }
        }

        // If the rotation is 360 degrees then add no rotation.
        if ($rotate == 360) {
            $rotate = 0;
        }

        // Subtract 360 degrees if the rotation is higher than 270.
        if ($rotate > 270) {
            $rotate -= 360;
        }

        return [$rotate, $flip, $flop];
    }

    /**
     * Convert interpretation to colourspace
     *
     * @param int $interpretation The VipsInterpretation
     *
     * @return string The colourspace
     */
    public static function interpretationToColourSpace(int $interpretation): string
    {
        return self::VIPS_INTERPRETATION_TO_COLOURSPACE[$interpretation];
    }

    /**
     * Convert a number range to another range, maintaining ratio
     *
     * @param int $value
     * @param int $in_min
     * @param int $in_max
     * @param int $out_min
     * @param int $out_max
     *
     * @return float
     */
    public static function mapToRange(int $value, int $in_min, int $in_max, int $out_min, int $out_max): float
    {
        return (float)($value - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;
    }
}
