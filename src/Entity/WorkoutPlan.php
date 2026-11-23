<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\Entity;

use Youmad\Endurance\Foundation\ValueObject\Instant;
use Youmad\Endurance\Workout\Exception\WorkoutConflict;
use Youmad\Endurance\Workout\ValueObject\ExpectedWorkoutState;
use Youmad\Endurance\Workout\ValueObject\WorkoutId;
use Youmad\Endurance\Workout\ValueObject\WorkoutRevisionId;
use Youmad\Endurance\Workout\ValueObject\WorkoutRevisionReference;
use Youmad\Endurance\Workout\ValueObject\WorkoutVersion;

final readonly class WorkoutPlan
{
    public function __construct(
        public WorkoutId $id,
        public WorkoutRevisionId $currentRevisionId,
        public WorkoutVersion $version,
        public Instant $createdAt,
        public Instant $updatedAt,
    ) {
        if ($updatedAt->isBefore($createdAt)) {
            throw new \InvalidArgumentException('Workout update cannot predate creation.');
        }
    }

    public static function create(WorkoutRevision $revision): self
    {
        return new self($revision->workoutId, $revision->id, new WorkoutVersion(1), $revision->createdAt, $revision->createdAt);
    }

    public function reference(): WorkoutRevisionReference
    {
        return new WorkoutRevisionReference($this->id, $this->currentRevisionId);
    }

    public function expectation(): ExpectedWorkoutState
    {
        return new ExpectedWorkoutState($this->version, $this->currentRevisionId);
    }

    public function assertExpected(ExpectedWorkoutState $expected): void
    {
        if (!$this->version->equals($expected->version) || !$this->currentRevisionId->equals($expected->revisionId)) {
            throw new WorkoutConflict('Workout head changed since the expected state was read.');
        }
    }

    public function withRevision(WorkoutRevision $replacement, ExpectedWorkoutState $expected): self
    {
        $this->assertExpected($expected);
        if (!$replacement->workoutId->equals($this->id) || $replacement->id->equals($this->currentRevisionId)) {
            throw new \InvalidArgumentException('Replacement must be a new revision of the same workout.');
        }
        if ($replacement->createdAt->isBefore($this->updatedAt)) {
            throw new \InvalidArgumentException('Workout publication time cannot move backwards.');
        }

        return new self($this->id, $replacement->id, $this->version->next(), $this->createdAt, $replacement->createdAt);
    }
}
