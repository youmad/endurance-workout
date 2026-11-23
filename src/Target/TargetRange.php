<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\Target;

/** Bounds are inclusive and use the metric's declared unit. */
final readonly class TargetRange
{
    public function __construct(public TargetMetric $metric, public float $minimum, public float $maximum)
    {
        if (!is_finite($minimum) || !is_finite($maximum) || $minimum < 0 || $maximum < $minimum) {
            throw new \InvalidArgumentException('A target requires a finite nonnegative ordered range.');
        }
    }
}
