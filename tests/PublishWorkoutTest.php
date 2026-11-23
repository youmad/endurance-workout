<?php

declare(strict_types=1);

namespace Endurance\Workout\Tests;

use Endurance\Foundation\Clock\FrozenClock;
use Endurance\Foundation\ValueObject\Instant;
use Endurance\Workout\Application\Port\WorkoutRepository;
use Endurance\Workout\Application\UseCase\PublishWorkout;
use Endurance\Workout\Duration\OpenDuration;
use Endurance\Workout\Entity\WorkoutPlan;
use Endurance\Workout\Entity\WorkoutRevision;
use Endurance\Workout\Exception\WorkoutConflict;
use Endurance\Workout\Sequence;
use Endurance\Workout\Step;
use Endurance\Workout\ValueObject\ExpectedWorkoutState;
use Endurance\Workout\ValueObject\WorkoutId;
use Endurance\Workout\ValueObject\WorkoutRevisionId;
use Endurance\Workout\ValueObject\WorkoutVersion;
use Endurance\Workout\Workout;
use PHPUnit\Framework\TestCase;

final class PublishWorkoutTest extends TestCase
{
    public function testCreationUsesCallerIdentitiesAndThePublicationClock(): void
    {
        $clock = new FrozenClock(Instant::fromDateTimeImmutable(new \DateTimeImmutable('2026-11-07T12:00:00.123456+05:00')));
        $id = WorkoutId::generate();
        $revisionId = WorkoutRevisionId::generate();
        $value = new Workout(new Sequence(new Step(new OpenDuration())), 'Plan');
        $repository = $this->createMock(WorkoutRepository::class);
        $repository->expects(self::once())->method('publishNew')->willReturnCallback(static function (WorkoutRevision $revision) use ($id, $revisionId, $value, $clock): WorkoutPlan {
            self::assertSame($id, $revision->workoutId);
            self::assertSame($revisionId, $revision->id);
            self::assertSame($value, $revision->prescription);
            self::assertTrue($clock->now()->equals($revision->createdAt));

            return WorkoutPlan::create($revision);
        });
        $plan = new PublishWorkout($repository, $clock)->create($id, $revisionId, $value);
        self::assertSame(1, $plan->version->value);
    }

    public function testRevisionForwardsTheExactExpectationAndDoesNotRetryAConflict(): void
    {
        $clock = new FrozenClock(Instant::fromDateTimeImmutable(new \DateTimeImmutable('2026-11-07T12:00:00Z')));
        $id = WorkoutId::generate();
        $revision = WorkoutRevisionId::generate();
        $expected = new ExpectedWorkoutState(new WorkoutVersion(7), WorkoutRevisionId::generate());
        $value = new Workout(new Sequence(new Step(new OpenDuration())));
        $conflict = new WorkoutConflict('another writer');
        $repository = $this->createMock(WorkoutRepository::class);
        $repository->expects(self::once())->method('publishReplacement')->with(self::callback(static fn (WorkoutRevision $r): bool => $r->workoutId->equals($id) && $r->id->equals($revision) && $r->prescription === $value && $r->createdAt->equals($clock->now())), self::identicalTo($expected))->willThrowException($conflict);
        try {
            new PublishWorkout($repository, $clock)->revise($id, $expected, $revision, $value);
            self::fail('A publication conflict was hidden.');
        } catch (WorkoutConflict $failure) {
            self::assertSame($conflict, $failure);
        }
    }

    public function testAnUncertainWriteIsNotRetriedWithANewIdentity(): void
    {
        $clock = new FrozenClock(Instant::fromDateTimeImmutable(new \DateTimeImmutable('2026-11-07T12:00:00Z')));
        $id = WorkoutId::generate();
        $revision = WorkoutRevisionId::generate();
        $value = new Workout(new Sequence(new Step(new OpenDuration())));
        $uncertain = new \RuntimeException('lost acknowledgement');
        $repository = $this->createMock(WorkoutRepository::class);
        $repository->expects(self::once())->method('publishNew')->with(self::callback(static fn (WorkoutRevision $r): bool => $r->id->equals($revision) && $r->workoutId->equals($id)))->willThrowException($uncertain);
        try {
            new PublishWorkout($repository, $clock)->create($id, $revision, $value);
            self::fail('An uncertain write was hidden.');
        } catch (\RuntimeException $failure) {
            self::assertSame($uncertain, $failure);
        }
    }
}
