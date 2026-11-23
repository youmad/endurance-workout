<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\Duration;

final readonly class DistanceDuration
{
    public function __construct(public float $metres)
    {
        if (!is_finite($metres) || $metres <= 0) {
            throw new \InvalidArgumentException('A distance step requires finite positive metres.');
        }
    }
}
