<?php

declare(strict_types=1);

namespace Endurance\Workout\Application\UseCase;

use Endurance\Foundation\Clock\Clock;
use Endurance\Workout\Application\Port\WorkoutRepository;
use Endurance\Workout\Entity\WorkoutPlan;
use Endurance\Workout\Entity\WorkoutRevision;
use Endurance\Workout\ValueObject\ExpectedWorkoutState;
use Endurance\Workout\ValueObject\WorkoutId;
use Endurance\Workout\ValueObject\WorkoutRevisionId;
use Endurance\Workout\Workout;

final readonly class PublishWorkout
{
    public function __construct(private WorkoutRepository $workouts, private Clock $clock)
    {
    }

    public function create(WorkoutId $id, WorkoutRevisionId $revisionId, Workout $prescription): WorkoutPlan
    {
        return $this->workouts->publishNew(new WorkoutRevision($revisionId, $id, $prescription, $this->clock->now()));
    }

    public function revise(WorkoutId $id, ExpectedWorkoutState $expected, WorkoutRevisionId $revisionId, Workout $prescription): WorkoutPlan
    {
        return $this->workouts->publishReplacement(new WorkoutRevision($revisionId, $id, $prescription, $this->clock->now()), $expected);
    }
}
