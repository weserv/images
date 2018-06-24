<?php

namespace Weserv\Images\Manipulators;

use Jcupitt\Vips\Access;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;

/**
 * @property string $sharp
 * @property bool $isPremultiplied
 * @property string $accessMethod
 */
class Sharpen extends BaseManipulator
{
    /**
     * Perform sharpen image manipulation.
     *
     * @param Image $image The source image.
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     */
    public function run(Image $image): Image
    {
        if (!isset($this->sharp)) {
            return $image;
        }

        if (!$this->isPremultiplied && $image->hasAlpha()) {
            // Premultiply image alpha channel before sharpen transformation
            $image = $image->premultiply();
            $this->isPremultiplied = true;
        }

        [$flat, $jagged, $sigma] = $this->getSharpen();

        $image = $this->sharpen($image, $sigma, $flat, $jagged);

        return $image;
    }

    /**
     * Resolve sharpen amount.
     *
     * @return float[] The resolved sharpen amount.
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getSharpen(): array
    {
        $sharpPieces = explode(',', $this->sharp);
        $sharpenFlat = 1.0;
        $sharpenJagged = 2.0;
        $sharpenSigma = -1.0;

        // Control over flat areas
        if (isset($sharpPieces[0])) {
            $flat = (float)$sharpPieces[0];
            if ($flat > 0 && $flat <= 10000) {
                $sharpenFlat = $flat;
            }
        }

        // Control over jagged areas
        if (isset($sharpPieces[1])) {
            $jagged = (float)$sharpPieces[1];
            if ($jagged > 0 && $jagged <= 10000) {
                $sharpenJagged = $jagged;
            }
        }

        // Specific sigma
        if (isset($sharpPieces[2])) {
            $sigma = (float)$sharpPieces[2];
            if ($sigma > 0 && $sigma <= 10000) {
                $sharpenSigma = $sigma;
            }
        }

        return [$sharpenFlat, $sharpenJagged, $sharpenSigma];
    }

    /**
     * Sharpen flat and jagged areas. Use sigma of -1.0 for fast sharpen.
     *
     * @param Image $image The source image.
     * @param float $sigma Sharpening mask to apply in pixels, but comes at a performance cost. (Default: -1)
     * @param float $flat Sharpening to apply to flat areas. (Default: 1.0)
     * @param float $jagged Sharpening to apply to jagged areas. (Default: 2.0)
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return Image The manipulated image.
     */
    public function sharpen(Image $image, float $sigma, float $flat, float $jagged): Image
    {
        if ($sigma === -1.0) {
            // Fast, mild sharpen
            $matrix = Image::newFromArray([
                [-1.0, -1.0, -1.0],
                [-1.0, 32, -1.0],
                [-1.0, -1.0, -1.0]
            ], 24.0);

            return $image->conv($matrix);
        }

        // Slow, accurate sharpen in LAB colour space, with control over flat vs jagged areas
        $oldInterpretation = $image->interpretation;
        if ($oldInterpretation === Interpretation::RGB) {
            $oldInterpretation = Interpretation::SRGB;
        }

        if ($this->accessMethod === Access::SEQUENTIAL) {
            $image = $image->linecache([
                'tile_height' => 10,
                'access' => Access::SEQUENTIAL,
                'threaded' => true
            ]);
        }

        return $image->sharpen([
            'sigma' => $sigma,
            'm1' => $flat,
            'm2' => $jagged
        ])->colourspace($oldInterpretation);
    }
}
