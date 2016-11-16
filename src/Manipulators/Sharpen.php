<?php

namespace AndriesLouw\imagesweserv\Manipulators;

use Jcupitt\Vips\Interpretation;
use Jcupitt\Vips\Image;

/**
 * @property string $sharp
 * @property bool $hasAlpha
 * @property int $maxAlpha
 * @property bool $isPremultiplied
 */
class Sharpen extends BaseManipulator
{
    /**
     * Perform sharpen image manipulation.
     *
     * @param  Image $image The source image.
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if ($this->sharp === null) {
            return $image;
        }

        if ($this->hasAlpha && !$this->isPremultiplied) {
            // Ensures that the image alpha channel is premultiplied before doing any sharpen transformations
            // to avoid dark fringing around bright pixels
            // See: http://entropymine.com/imageworsener/resizealpha/
            $image = $image->premultiply(['max_alpha' => $this->maxAlpha]);
            $this->isPremultiplied = true;
        }

        list($flat, $jagged, $sigma) = $this->getSharpen();

        $image = $this->sharpen($image, $sigma, $flat, $jagged);

        return $image;
    }

    /**
     * Resolve sharpen amount.
     *
     * @return array The resolved sharpen amount.
     */
    public function getSharpen(): array
    {
        $sharpPieces = explode(',', $this->sharp);
        $sharpenFlat = 1.0;
        $sharpenJagged = 2.0;
        $sharpenSigma = -1;

        if (isset($sharpPieces[0])) {
            $flat = intval($sharpPieces[0]);
            if ($flat > 0 && $flat <= 10000) {
                $sharpenFlat = $flat;
            }
        }

        if (isset($sharpPieces[1])) {
            $jagged = intval($sharpPieces[1]);
            if ($jagged > 0 && $jagged <= 10000) {
                $sharpenJagged = $jagged;
            }
        }

        if (isset($sharpPieces[2])) {
            $sigma = floatval($sharpPieces[2]);
            if ($sigma >= 0.01 && $sigma <= 10000) {
                $sharpenSigma = $sigma;
            }
        }

        return [$sharpenFlat, $sharpenJagged, $sharpenSigma];
    }

    /**
     * Sharpen flat and jagged areas. Use sigma of -1.0 for fast sharpen.
     *
     * @param  Image $image The source image.
     * @param  float $sigma Sharpening mask to apply in pixels, but comes at a performance cost. (Default: -1)
     * @param  int $flat Sharpening to apply to flat areas. (Default: 1.0)
     * @param  int $jagged Sharpening to apply to jagged areas. (Default: 2.0)
     *
     * @return Image The manipulated image.
     */
    public function sharpen($image, $sigma, $flat, $jagged): Image
    {
        if ($sigma == -1.0) {
            // Fast, mild sharpen
            $matrix = Image::newFromArray(
                [
                    [-1.0, -1.0, -1.0],
                    [-1.0, 32, -1.0],
                    [-1.0, -1.0, -1.0]
                ],
                24.0
            );

            return $image->conv($matrix);
        } else {
            // Slow, accurate sharpen in LAB colour space, with control over flat vs jagged areas
            $oldInterpretation = $image->interpretation;
            if ($oldInterpretation == Interpretation::RGB) {
                $oldInterpretation = Interpretation::SRGB;
            }

            return $image->sharpen(
                [
                    "sigma" => $sigma,
                    "m1" => $flat,
                    "m2" => $jagged
                ]
            )->colourspace($oldInterpretation);
        }
    }
}
