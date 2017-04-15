<?php

namespace AndriesLouw\imagesweserv\Manipulators\Helpers;

use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;

class Utils
{
    /**
     * The orientation tag for this image. An int from 1 - 8 using the standard
     * exif/tiff meanings.
     */
    const VIPS_META_ORIENTATION = 'orientation';

    /**
     * The name we use to attach an ICC profile. The file read and write
     * operations for TIFF, JPEG, PNG and others use this item of metadata to
     * attach and save ICC profiles. The profile is updated by the
     * vips_icc_transform() operations.
     */
    const VIPS_META_ICC_NAME = 'icc-profile-data';

    /**
     * Are pixel values in this image 16-bit integer?
     *
     * @param  string $interpretation The VipsInterpretation
     *
     * @return bool indicating if the pixel values in this image are 16-bit
     */
    public static function is16Bit(string $interpretation): bool
    {
        return $interpretation === Interpretation::RGB16
            || $interpretation === Interpretation::GREY16;
    }

    /**
     * Does this image have an embedded profile?
     *
     * @param  Image $image The source image.
     *
     * @return bool indicating if this image have an embedded profile
     */
    public static function hasProfile(Image $image): bool
    {
        return $image->typeof(self::VIPS_META_ICC_NAME) !== 0;
    }

    /**
     * Return the image alpha maximum. Useful for combining alpha bands. scRGB
     * images are 0 - 1 for image data, but the alpha is 0 - 255.
     *
     * @param  string $interpretation The VipsInterpretation
     *
     * @return int the image alpha maximum
     */
    public static function maximumImageAlpha(string $interpretation): int
    {
        return self::is16Bit($interpretation) ? 65535 : 255;
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
            return $image->get(self::VIPS_META_ORIENTATION);
        }
        return 0;
    }

    /**
     * Get the angle of rotation from our EXIF metadata.
     *
     * @param  Image $image The source image.
     *
     * @return int rotation
     */
    public static function resolveExifOrientation(Image $image): int
    {
        $rotate = 0;

        $exifOrientation = self::exifOrientation($image);
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
        }

        return $rotate;
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

    /**
     * Determine image extension from the name of the load operation
     *
     * @param string|null $loader The name of the load operation
     *
     * @return string image type
     */
    public static function determineImageExtension($loader)
    {
        switch ($loader) {
            case 'VipsForeignLoadJpegFile':
                return 'jpg';
            case 'VipsForeignLoadPng':
                return 'png';
            case 'VipsForeignLoadWebpFile':
                return 'webp';
            case 'VipsForeignLoadTiffFile':
                return 'tiff';
            case 'VipsForeignLoadGifFile':
                return 'gif';
            case 'VipsForeignLoadSvgFile':
                return 'svg';
            case 'VipsForeignLoadPdfFile':
                return 'pdf';
            case 'VipsForeignLoadRaw':
                return 'raw';
            case 'VipsForeignLoadMagickFile':
                // Not a extension
                return 'magick';
            case 'VipsForeignLoadOpenexr':
                return 'exr';
            case 'VipsForeignLoadMat':
                return 'mat';
            case 'VipsForeignLoadRad':
                return 'hdr';
            case 'VipsForeignLoadPpm':
                return 'ppm';
            case 'VipsForeignLoadFits':
                return 'fits';
            case 'VipsForeignLoadVips':
                return 'v';
            case 'VipsForeignLoadAnalyze':
                return 'img';
            case 'VipsForeignLoadCsv':
                return 'csv';
            case 'VipsForeignLoadMatrix':
                return 'txt';
            default:
                return 'unknown';
        }
    }

    /**
     * http://stackoverflow.com/questions/5501427/php-filesize-mb-kb-conversion
     *
     * @param int $bytes
     *
     * @return string
     */
    public static function formatSizeUnits(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        if ($bytes > 1) {
            return $bytes . ' bytes';
        }
        if ($bytes === 1) {
            return '1 byte';
        }

        return '0 bytes';
    }
}
