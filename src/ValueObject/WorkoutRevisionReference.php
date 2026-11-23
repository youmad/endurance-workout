<?php

declare(strict_types=1);

namespace Endurance\Workout\ValueObject;

final readonly class WorkoutRevisionReference
{
    public function __construct(public WorkoutId $workoutId, public WorkoutRevisionId $revisionId)
    {
    }

    public function equals(self $other): bool
    {
        return $this->workoutId->equals($other->workoutId) && $this->revisionId->equals($other->revisionId);
    }
}
