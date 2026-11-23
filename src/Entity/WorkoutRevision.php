<?php

declare(strict_types=1);

namespace Endurance\Workout\Entity;

use Endurance\Foundation\ValueObject\Instant;
use Endurance\Workout\ValueObject\WorkoutId;
use Endurance\Workout\ValueObject\WorkoutRevisionId;
use Endurance\Workout\ValueObject\WorkoutRevisionReference;
use Endurance\Workout\Workout;

final readonly class WorkoutRevision
{
    public function __construct(
        public WorkoutRevisionId $id,
        public WorkoutId $workoutId,
        public Workout $prescription,
        public Instant $createdAt,
    ) {
    }

    public function reference(): WorkoutRevisionReference
    {
        return new WorkoutRevisionReference($this->workoutId, $this->id);
    }
}
