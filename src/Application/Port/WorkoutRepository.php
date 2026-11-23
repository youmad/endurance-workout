<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\Application\Port;

use Youmad\Endurance\Workout\Entity\WorkoutPlan;
use Youmad\Endurance\Workout\Entity\WorkoutRevision;
use Youmad\Endurance\Workout\ValueObject\ExpectedWorkoutState;
use Youmad\Endurance\Workout\ValueObject\WorkoutId;
use Youmad\Endurance\Workout\ValueObject\WorkoutRevisionReference;

/** Unscoped persistence. Authorization belongs to the calling application. */
interface WorkoutRepository
{
    public function get(WorkoutId $id): WorkoutPlan;

    public function getRevision(WorkoutRevisionReference $reference): WorkoutRevision;

    /** Atomically create the first head and immutable snapshot; never reuse a stored revision ID. */
    public function publishNew(WorkoutRevision $revision): WorkoutPlan;

    /** Atomically compare BOTH expected fields, insert a fresh snapshot and advance the head once. */
    public function publishReplacement(WorkoutRevision $revision, ExpectedWorkoutState $expected): WorkoutPlan;
}
