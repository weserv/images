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
            $exif = $image->get(self::VIPS_META_ORIENTATION);
            return $exif;
        }
        return 0;
    }

    /**
     * Resolve crop coordinates.
     *
     * @param  array $params Image manipulation params.
     * @param  Image $image The source image.
     *
     * @return array|null The resolved coordinates.
     */
    public static function resolveCropCoordinates(array $params, Image $image)
    {
        if (!isset($params['crop'])) {
            return null;
        }

        $coordinates = explode(',', $params['crop']);

        if (count($coordinates) !== 4
            || (!is_numeric($coordinates[0]))
            || (!is_numeric($coordinates[1]))
            || (!is_numeric($coordinates[2]))
            || (!is_numeric($coordinates[3]))
            || ($coordinates[0] <= 0)
            || ($coordinates[1] <= 0)
            || ($coordinates[2] < 0)
            || ($coordinates[3] < 0)
            || ($coordinates[2] >= $image->width)
            || ($coordinates[3] >= $image->height)
        ) {
            return null;
        }

        return [
            (int)$coordinates[0],
            (int)$coordinates[1],
            (int)$coordinates[2],
            (int)$coordinates[3],
        ];
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
        $validOrientationArr = ['90' => 0, '180' => 1, '270' => 2];
        if (isset($params['or']) && isset($validOrientationArr[$params['or']])) {
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
        if ($angle === 90) {
            $rotate += 90;
        } else {
            if ($angle === 180) {
                $rotate += 180;
            } else {
                if ($angle === 270) {
                    $rotate += 270;
                }
            }
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
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes === 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
}
