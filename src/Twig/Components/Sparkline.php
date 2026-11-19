<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Chart\SparklineGeometry;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A small line of the last months in a tile. It is decoration, so screen readers skip it.
 */
#[AsTwigComponent]
final class Sparkline
{
    /**
     * @var list<float|null>
     */
    public array $values = [];

    /**
     * The class that gives the colour, for example "series-info".
     */
    public string $cssClass = 'series-blue';

    public function path(): ?string
    {
        return SparklineGeometry::path($this->values);
    }
}
