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
     * @param string $interpretation The VipsInterpretation
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
     * @param Image $image The source image.
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
     * @param string $interpretation The VipsInterpretation
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
     * @throws \Jcupitt\Vips\Exception
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
     * Resolve an explicit angle.
     *
     * If an angle is provided, it is converted to a valid 90/180/270deg rotation.
     * For example, `-450` will produce a 270deg rotation.
     *
     * @param string|int $angle Angle of rotation, must be a multiple of 90.
     *
     * @return int rotation
     */
    public static function resolveAngleRotation($angle): int
    {
        if (!is_numeric($angle)) {
            return 0;
        }

        // Check if is not a multiple of 90
        if ($angle % 90 !== 0) {
            return 0;
        }

        // Calculate the rotation for the given angle that is a multiple of 90
        $angle %= 360;

        if ($angle < 0) {
            $angle += 360;
        }

        return $angle;
    }

    /**
     * Calculate the angle of rotation and need-to-flip for the given exif orientation
     * and parameters
     *
     * @param Image $image The source image.
     * @param  array $params Parameters array
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return array [rotation, flip, flop]
     */
    public static function resolveRotationAndFlip(Image $image, array $params): array
    {
        $rotate = isset($params['or']) ? self::resolveAngleRotation($params['or']) : 0;
        $flip = isset($params['flip']) || array_key_exists('flip', $params);
        $flop = isset($params['flop']) || array_key_exists('flop', $params);

        $exifOrientation = self::exifOrientation($image);
        switch ($exifOrientation) {
            case 6:
                $rotate += 90;
                break;
            case 3:
                $rotate += 180;
                break;
            case 8:
                $rotate += 270;
                break;
            case 2: // flop 1
                $flop = true;
                break;
            case 7: // flip 6
                $flip = true;
                $rotate += 90;
                break;
            case 4: // flop 3
                $flop = true;
                $rotate += 180;
                break;
            case 5: // flip 8
                $flip = true;
                $rotate += 270;
                break;
        }

        $rotate %= 360;

        return [$rotate, $flip, $flop];
    }

    /**
     * Determine image extension from the name of the load operation
     *
     * @param string $loader The name of the load operation
     *
     * @return string image extension
     */
    public static function determineImageExtension(string $loader): string
    {
        $extension = 'unknown';

        switch ($loader) {
            case 'jpegload':
                $extension = 'jpg';
                break;
            case 'pngload':
                $extension = 'png';
                break;
            case 'webpload':
                $extension = 'webp';
                break;
            case 'tiffload':
                $extension = 'tiff';
                break;
            case 'gifload':
                $extension = 'gif';
                break;
        }

        return $extension;
    }

    /**
     * https://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
     *
     * @param int $bytes
     * @param int $precision
     *
     * @return string
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);

        $bytes /= 1024 ** $pow;

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
