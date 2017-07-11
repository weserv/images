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
     * @param  array $params Image manipulation params.
     * @param  Image $image The source image.
     *
     * @return array [rotation, flip, flop]
     */
    public static function calculateRotationAndFlip(array $params, Image $image): array
    {
        $angle = 0;
        if (isset($params['or']) && (
                $params['or'] === '90' ||
                $params['or'] === '180' ||
                $params['or'] === '270')
        ) {
            $angle = (int)$params['or'];
        }

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
        if ($angle === 90 || $angle === 180 || $angle === 270) {
            $rotate += $angle;
        }

        // If the rotation is 360 degrees then add no rotation.
        if ($rotate === 360) {
            $rotate = 0;
        }

        // Subtract 360 degrees if the rotation is higher than 270.
        if ($rotate > 270) {
            $rotate -= 360;
        }

        return [$rotate, $flip, $flop];
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
