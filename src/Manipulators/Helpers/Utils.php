<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

use Jcupitt\Vips\Image;

class Utils
{
    const EXIF_ORIENTATION = 'orientation';

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
    const VIPS_INTERPRETATION_sRGB = 22;
    const VIPS_INTERPRETATION_YXY = 23;
    const VIPS_INTERPRETATION_FOURIER = 24;
    const VIPS_INTERPRETATION_RGB16 = 25;
    const VIPS_INTERPRETATION_GREY16 = 26;
    const VIPS_INTERPRETATION_MATRIX = 27;
    const VIPS_INTERPRETATION_scRGB = 28;
    const VIPS_INTERPRETATION_HSV = 29;
    const VIPS_INTERPRETATION_LAST = 30;

    const VIPS_COLOURSPACE_ERROR = 'error';
    const VIPS_COLOURSPACE_MULTIBAND = 'multiband';
    const VIPS_COLOURSPACE_B_W = 'b-w';
    const VIPS_COLOURSPACE_HISTOGRAM = 'histogram';
    const VIPS_COLOURSPACE_XYZ = 'xyz';
    const VIPS_COLOURSPACE_LAB = 'lab';
    const VIPS_COLOURSPACE_CMYK = 'cmyk';
    const VIPS_COLOURSPACE_LABQ = 'labq';
    const VIPS_COLOURSPACE_RGB = 'rgb';
    const VIPS_COLOURSPACE_CMC = 'cmc';
    const VIPS_COLOURSPACE_LCH = 'lch';
    const VIPS_COLOURSPACE_LABS = 'labs';
    const VIPS_COLOURSPACE_sRGB = 'srgb';
    const VIPS_COLOURSPACE_YXY = 'yxy';
    const VIPS_COLOURSPACE_FOURIER = 'fourier';
    const VIPS_COLOURSPACE_RGB16 = 'rgb16';
    const VIPS_COLOURSPACE_GREY16 = 'grey16';
    const VIPS_COLOURSPACE_MATRIX = 'matrix';
    const VIPS_COLOURSPACE_scRGB = 'scrgb';
    const VIPS_COLOURSPACE_HSV = 'hsv';
    const VIPS_COLOURSPACE_LAST = 'last';

    const VIPS_FORMAT_NOTSET = -1;
    const VIPS_FORMAT_UCHAR = 0;
    const VIPS_FORMAT_CHAR = 1;
    const VIPS_FORMAT_USHORT = 2;
    const VIPS_FORMAT_SHORT = 3;
    const VIPS_FORMAT_UINT = 4;
    const VIPS_FORMAT_INT = 5;
    const VIPS_FORMAT_FLOAT = 6;
    const VIPS_FORMAT_COMPLEX = 7;
    const VIPS_FORMAT_DOUBLE = 8;
    const VIPS_FORMAT_DPCOMPLEX = 9;
    const VIPS_FORMAT_LAST = 10;

    const VIPS_INTERPRETATION_TO_COLOURSPACE = [
        self::VIPS_INTERPRETATION_ERROR => self::VIPS_COLOURSPACE_ERROR,
        self::VIPS_INTERPRETATION_MULTIBAND => self::VIPS_COLOURSPACE_MULTIBAND,
        self::VIPS_INTERPRETATION_B_W => self::VIPS_COLOURSPACE_B_W,
        self::VIPS_INTERPRETATION_HISTOGRAM => self::VIPS_COLOURSPACE_HISTOGRAM,
        self::VIPS_INTERPRETATION_XYZ => self::VIPS_COLOURSPACE_XYZ,
        self::VIPS_INTERPRETATION_LAB => self::VIPS_COLOURSPACE_LAB,
        self::VIPS_INTERPRETATION_CMYK => self::VIPS_COLOURSPACE_CMYK,
        self::VIPS_INTERPRETATION_LABQ => self::VIPS_COLOURSPACE_LABQ,
        self::VIPS_INTERPRETATION_RGB => self::VIPS_COLOURSPACE_RGB,
        self::VIPS_INTERPRETATION_CMC => self::VIPS_COLOURSPACE_CMC,
        self::VIPS_INTERPRETATION_LCH => self::VIPS_COLOURSPACE_LCH,
        self::VIPS_INTERPRETATION_LABS => self::VIPS_COLOURSPACE_LABS,
        self::VIPS_INTERPRETATION_sRGB => self::VIPS_COLOURSPACE_sRGB,
        self::VIPS_INTERPRETATION_YXY => self::VIPS_COLOURSPACE_YXY,
        self::VIPS_INTERPRETATION_FOURIER => self::VIPS_COLOURSPACE_FOURIER,
        self::VIPS_INTERPRETATION_RGB16 => self::VIPS_COLOURSPACE_RGB16,
        self::VIPS_INTERPRETATION_GREY16 => self::VIPS_COLOURSPACE_GREY16,
        self::VIPS_INTERPRETATION_MATRIX => self::VIPS_COLOURSPACE_MATRIX,
        self::VIPS_INTERPRETATION_scRGB => self::VIPS_COLOURSPACE_scRGB,
        self::VIPS_INTERPRETATION_HSV => self::VIPS_COLOURSPACE_HSV,
        self::VIPS_INTERPRETATION_LAST => self::VIPS_COLOURSPACE_LAST
    ];

    const VIPS_ANGLE_D0 = 'd0';
    const VIPS_ANGLE_D90 = 'd90';
    const VIPS_ANGLE_D180 = 'd180';
    const VIPS_ANGLE_D270 = 'd270';

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
        if ($image->typeof(self::EXIF_ORIENTATION) !== 0) {
            $exif = $image->get(self::EXIF_ORIENTATION);
            return $exif;
        }
        return 0;
    }


    /**
     * Set EXIF Orientation of image.
     *
     * @param Image $image The source image.
     * @param int $orientation EXIF Orientation.
     *
     * @return void
     */
    public static function setExifOrientation(Image $image, int $orientation)
    {
        $image->set(self::EXIF_ORIENTATION, $orientation);
    }

    /**
     * Remove EXIF Orientation from image.
     *
     * @param Image $image The source image.
     *
     * @return void
     */
    public static function removeExifOrientation(Image $image)
    {
        self::setExifOrientation($image, 1);
    }

    /**
     * Calculate the angle of rotation and need-to-flip for the output image.
     * In order of priority:
     *  1. Use explicitly requested angle (supports 90, 180, 270)
     *  2. Use input image EXIF Orientation header - supports mirroring
     *  3. Otherwise default to zero, i.e. no rotation
     *
     * @param  int $angle explicitly requested angle
     * @param  Image $image The source image.
     *
     * @return array [rotation, flip, flop]
     */
    public static function calculateRotationAndFlip(int $angle, Image $image): array
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
     * @param $value
     * @param $in_min
     * @param $in_max
     * @param $out_min
     * @param $out_max
     *
     * @return float|int
     */
    public static function mapToRange($value, $in_min, $in_max, $out_min, $out_max)
    {
        return ($value - $in_min) * ($out_max - $out_min) / ($in_max - $in_min) + $out_min;
    }
}
