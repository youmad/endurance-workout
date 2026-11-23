<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\Application\UseCase;

use Youmad\Endurance\Foundation\Clock\Clock;
use Youmad\Endurance\Workout\Application\Port\WorkoutRepository;
use Youmad\Endurance\Workout\Entity\WorkoutPlan;
use Youmad\Endurance\Workout\Entity\WorkoutRevision;
use Youmad\Endurance\Workout\ValueObject\ExpectedWorkoutState;
use Youmad\Endurance\Workout\ValueObject\WorkoutId;
use Youmad\Endurance\Workout\ValueObject\WorkoutRevisionId;
use Youmad\Endurance\Workout\Workout;

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
