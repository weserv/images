<?php

namespace Weserv\Images\Test;

use Jcupitt\Vips\Access;
use Jcupitt\Vips\Image;
use PHPUnit\Framework\Constraint\Constraint;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * Verify the maximum color distance
 */
class MaxColorDistanceConstraint extends Constraint
{
    /**
     * The expected image.
     */
    protected Image $expectedImage;

    /**
     * Expected maximum color distance. Defaulting to 1.
     */
    protected float $acceptedDistance;

    /**
     * The maximum color distance.
     */
    protected float $maxColorDistance;

    /**
     * MaxColorDistanceConstraint constructor.
     *
     * @param string|Image $image
     * @param float $acceptedDistance
     *
     * @throws \Jcupitt\Vips\Exception
     */
    public function __construct($image, float $acceptedDistance)
    {
        $this->expectedImage = \is_string($image) ?
            Image::newFromFile($image, ['access' => Access::SEQUENTIAL]) : $image;
        $this->acceptedDistance = $acceptedDistance;
    }

    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     *
     * @param mixed $other Value or object to evaluate.
     *
     * @throws \Jcupitt\Vips\Exception
     *
     * @return bool
     */
    public function matches($other): bool
    {
        if (\is_string($other)) {
            $other = Image::newFromFile($other, ['access' => Access::SEQUENTIAL]);
        }

        $this->maxColorDistance = $this->calculateMaxColorDistance($other, $this->expectedImage);

        return $this->maxColorDistance < $this->acceptedDistance;
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
        return 'actual image color distance ' .
            $this->maxColorDistance .
            ' is less than the expected maximum color distance ' .
            $this->acceptedDistance;
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
        $differ = new Differ(
            new UnifiedDiffOutputBuilder("--- Expected maximum color distance\n+++ Actual color distance\n")
        );
        return $differ->diff((string)$this->acceptedDistance, (string)$this->maxColorDistance);
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
     * Calculates the maximum color distance using the DE2000 algorithm
     * between two images of the same dimensions and number of channels.
     *
     * @param Image $image1 actual image
     * @param Image $image2 expected image
     *
     * @throws \InvalidArgumentException if mismatched bands or
     *      mismatched dimensions
     * @throws \Jcupitt\Vips\Exception
     *
     * @return float the maximum color distance
     */
    private function calculateMaxColorDistance(Image $image1, Image $image2): float
    {
        // Ensure same number of channels
        if ($image1->bands !== $image2->bands) {
            throw new \InvalidArgumentException('Mismatched bands');
        }

        // Ensure same dimensions
        if ($image1->width !== $image2->width || $image1->height !== $image2->height) {
            throw new \InvalidArgumentException('Mismatched dimensions');
        }

        // Premultiply and remove alpha
        if ($image1->hasAlpha()) {
            $image1 = $image1->premultiply()->extract_band(0, ['n' => $image1->bands - 1]);
        }
        if ($image2->hasAlpha()) {
            $image2 = $image2->premultiply()->extract_band(0, ['n' => $image2->bands - 1]);
        }

        // Calculate color distance
        return $image1->dE00($image2)->max();
    }
}
