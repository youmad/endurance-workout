<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\ValueObject;

final readonly class ExpectedWorkoutState
{
    public function __construct(public WorkoutVersion $version, public WorkoutRevisionId $revisionId)
    {
    }
}
