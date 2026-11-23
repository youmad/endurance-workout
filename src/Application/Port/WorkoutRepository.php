<?php

declare(strict_types=1);

namespace Endurance\Workout\Application\Port;

use Endurance\Workout\Entity\WorkoutPlan;
use Endurance\Workout\Entity\WorkoutRevision;
use Endurance\Workout\ValueObject\ExpectedWorkoutState;
use Endurance\Workout\ValueObject\WorkoutId;
use Endurance\Workout\ValueObject\WorkoutRevisionReference;

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
