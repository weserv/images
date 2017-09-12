<?php

namespace AndriesLouw\imagesweserv\Test;

use Jcupitt\Vips\Access;
use Jcupitt\Vips\Image;
use Jcupitt\Vips\Interpretation;
use PHPUnit\Framework\Constraint\Constraint;
use SebastianBergmann\Diff\Differ;

/**
 * Verify similarity of expected vs actual images
 */
class SimilarImageConstraint extends Constraint
{
    /*
     * The expected image
     * @var Image $expectedImage
     */
    protected $expectedImage;

    /*
     * Distance threshold. Defaulting to 5 (~7% threshold)
     * @var int $threshold
     */
    protected $threshold;

    /*
     * dHash distance.
     * @var int $distance
     */
    protected $distance;

    /*
     * Expected hash
     * @var string $expectedHash
     */
    protected $expectedHash;

    /*
     * Actual hash
     * @var string $actualHash
     */
    protected $actualHash;

    public function __construct($image, int $threshold)
    {
        parent::__construct();
        $this->expectedImage = is_string($image) ?
            Image::newFromFile($image, ['access' => Access::SEQUENTIAL]) : $image;
        $this->threshold = $threshold;
    }

    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     *
     * @param mixed $other Value or object to evaluate.
     *
     * @return bool
     */
    public function matches($other): bool
    {
        if (is_string($other)) {
            $other = Image::newFromFile($other, ['access' => Access::SEQUENTIAL]);
        }

        $this->expectedHash = $this->dHash($this->expectedImage);
        $this->actualHash = $this->dHash($other);

        $this->distance = $this->dHashDistance($this->expectedHash, $this->actualHash);

        return $this->distance < $this->threshold;
    }

    /**
     * Returns the description of the failure
     *
     * The beginning of failure messages is "Failed asserting that" in most
     * cases. This method should return the second part of that sentence.
     *
     * @param mixed $other Evaluated value or object.
     *
     * @return string
     */
    public function failureDescription($other): string
    {
        return 'actual image similarity distance ' . $this->distance . ' is less than the threshold ' . $this->threshold;
    }

    /**
     * Return additional failure description where needed
     *
     * @param mixed $other Evaluated value or object.
     *
     * @return string
     */
    public function additionalFailureDescription($other): string
    {
        $differ = new Differ("--- Expected hash\n+++ Actual hash\n");
        return $differ->diff($this->expectedHash, $this->actualHash);
    }

    /**
     * Returns a string representation of the constraint.
     *
     * @return string
     */
    public function toString(): string
    {
        return 'similar image';
    }

    /**
     * Stretch luminance to cover full dynamic range.
     *
     * @param Image $image
     *
     * @return Image
     */
    private function normalizeImage(Image $image): Image
    {
        // Get original colourspace
        $typeBeforeNormalize = $image->interpretation;
        if ($typeBeforeNormalize === Interpretation::RGB) {
            $typeBeforeNormalize = Interpretation::SRGB;
        }

        // Convert to LAB colourspace
        $lab = $image->colourspace(Interpretation::LAB);

        // Extract luminance
        $luminance = $lab->extract_band(0);

        // Find luminance range
        $stats = $luminance->stats();
        $min = $stats->getpoint(0, 0)[0];
        $max = $stats->getpoint(1, 0)[0];

        if ($min !== $max) {
            // Extract chroma
            $chroma = $lab->extract_band(1, ['n' => 2]);

            // Calculate multiplication factor and addition
            $f = 100.0 / ($max - $min);
            $a = -($min * $f);

            // Scale luminance, join to chroma, convert back to original colourspace
            $normalized = $luminance->linear($f, $a)->bandjoin($chroma)->colourspace($typeBeforeNormalize);

            // Attach original alpha channel, if any
            if ($image->hasAlpha()) {
                // Extract original alpha channel
                $alpha = $image->extract_band($image->bands - 1);

                // Join alpha channel to normalised image
                return $normalized->bandjoin($alpha);
            }
            return $normalized;
        }

        return $image;
    }

    /**
     * Calculate a perceptual hash of an image.
     * Based on the dHash gradient method - see http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
     *
     * @param Image $image
     *
     * @return string
     */
    private function dHash(Image $image): string
    {
        $thumbnailOptions = [
            'height' => 8,
            'size' => 'force',
            'auto_rotate' => false,
            'linear' => false
        ];

        /** @var Image $thumbnailImage */
        $thumbnailImage = $image->thumbnail_image(9, $thumbnailOptions)->colourspace(Interpretation::B_W);

        // TODO: Pull request https://github.com/jcupitt/php-vips-ext/pull/13 needs to be merged first.
        /*$dHashImage = $this->normalizeImage($thumbnailImage->copyMemory())->extract_band(0)->writeToMemory();

        // Calculate dHash
        $hash = 0;
        $bit = 1;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $left = $dHashImage[($x * 8) + $y];
                $right = $dHashImage[($x * 8) + $y + 1];

                // Each hash bit is set based on whether the left pixel is brighter than the right pixel
                if ($left > $right) {
                    $hash |= $bit;
                }

                // Prepare the next loop
                $bit <<= 1;
            }
        }*/

        $dHashImage = $this->normalizeImage($thumbnailImage->copyMemory())->extract_band(0);

        // Calculate dHash
        $hash = 0;
        $bit = 1;
        for ($y = 0; $y < 8; $y++) {
            // Get the pixel value for the leftmost pixel
            $previous = $dHashImage->getpoint(0, $y)[0];

            for ($x = 1; $x < 9; $x++) {
                $current = $dHashImage->getpoint($x, $y)[0];

                // Each hash bit is set based on whether the left pixel is brighter than the right pixel
                if ($previous > $current) {
                    $hash |= $bit;
                }

                // Prepare the next loop
                $bit <<= 1;
                $previous = $current;
            }
        }

        return sprintf('%016x', $hash);
    }

    /**
     * Calculates dHash hamming distance.
     *
     * See http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
     *
     * @param $hash1
     * @param $hash2
     *
     * @return int the number of bits different between two hash values.
     */
    private function dHashDistance($hash1, $hash2): int
    {
        // Nibble lookup table to reduce computation time, see https://stackoverflow.com/a/25808559/1480019
        $nibbleLookup = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];

        $res = 0;
        for ($i = 0; $i < 16; $i++) {
            if ($hash1[$i] !== $hash2[$i]) {
                $res += $nibbleLookup[hexdec($hash1[$i]) ^ hexdec($hash2[$i])];
            }
        }

        return $res;
    }
}