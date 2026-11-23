<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout;

final readonly class Repeat
{
    public int $occurrenceCount;

    public function __construct(public int $repetitions, public Sequence $steps)
    {
        if ($repetitions < 1 || $repetitions > intdiv(PHP_INT_MAX, $steps->occurrenceCount)) {
            throw new \InvalidArgumentException('Repeat count must be positive and its expanded count must fit a PHP integer.');
        }
        $this->occurrenceCount = $repetitions * $steps->occurrenceCount;
    }
}
