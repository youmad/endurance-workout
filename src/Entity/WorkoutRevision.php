<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\Entity;

use Youmad\Endurance\Foundation\ValueObject\Instant;
use Youmad\Endurance\Workout\ValueObject\WorkoutId;
use Youmad\Endurance\Workout\ValueObject\WorkoutRevisionId;
use Youmad\Endurance\Workout\ValueObject\WorkoutRevisionReference;
use Youmad\Endurance\Workout\Workout;

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
