<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout;

final readonly class Workout
{
    public function __construct(public Sequence $steps, public ?string $name = null, public ?string $description = null)
    {
    }
}
