<?php

declare(strict_types=1);

namespace Endurance\Workout\Duration;

final readonly class TimeDuration
{
    public function __construct(public int $microseconds)
    {
        if ($microseconds < 1) {
            throw new \InvalidArgumentException('A timed step requires positive microseconds.');
        }
    }
}
