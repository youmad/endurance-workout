<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Foundation\ValueObject\Instant;
use Youmad\Endurance\Workout\Duration\TimeDuration;
use Youmad\Endurance\Workout\Entity\WorkoutPlan;
use Youmad\Endurance\Workout\Entity\WorkoutRevision;
use Youmad\Endurance\Workout\Exception\WorkoutConflict;
use Youmad\Endurance\Workout\Repeat;
use Youmad\Endurance\Workout\Sequence;
use Youmad\Endurance\Workout\Step;
use Youmad\Endurance\Workout\ValueObject\ExpectedWorkoutState;
use Youmad\Endurance\Workout\ValueObject\WorkoutId;
use Youmad\Endurance\Workout\ValueObject\WorkoutRevisionId;
use Youmad\Endurance\Workout\ValueObject\WorkoutRevisionReference;
use Youmad\Endurance\Workout\ValueObject\WorkoutVersion;
use Youmad\Endurance\Workout\Workout;

final class WorkoutPlanTest extends TestCase
{
    private function time(string $value): Instant
    {
        return Instant::fromDateTimeImmutable(new \DateTimeImmutable($value));
    }

    private function revision(WorkoutId $id, int $repetitions = 2, string $time = '2026-11-06T10:00:00Z', ?WorkoutRevisionId $revisionId = null): WorkoutRevision
    {
        return new WorkoutRevision($revisionId ?? WorkoutRevisionId::generate(), $id,
            new Workout(new Sequence(new Repeat($repetitions, new Sequence(new Step(new TimeDuration(1))))), 'Plan '.$repetitions), $this->time($time));
    }

    public function testReplacingTheHeadPreservesTheOldPrescriptionAndItsOccurrenceScope(): void
    {
        $first = $this->revision(WorkoutId::generate());
        $original = WorkoutPlan::create($first);
        $pin = $first->reference();
        $second = $this->revision($original->id, 3, '2026-11-06T10:01:00Z');
        $updated = $original->withRevision($second, $original->expectation());
        self::assertTrue($original->id->equals($updated->id));
        self::assertSame(1, $original->version->value);
        self::assertSame(2, $updated->version->value);
        self::assertTrue($original->reference()->equals($pin));
        self::assertFalse($updated->reference()->equals($pin));
        self::assertSame(2, $first->prescription->steps->occurrenceCount);
        self::assertSame(3, $second->prescription->steps->occurrenceCount);
        self::assertSame('Plan 2', $first->prescription->name);
        self::assertTrue($updated->createdAt->equals($original->createdAt));
        self::assertTrue($updated->updatedAt->equals($second->createdAt));
        self::assertFalse($first->id->equals($second->id));
    }

    #[DataProvider('wrongExpectations')]
    public function testBothExpectedFieldsAreRequiredBeforeAHeadChange(bool $wrongVersion, bool $wrongRevision): void
    {
        $first = $this->revision(WorkoutId::generate());
        $plan = WorkoutPlan::create($first);
        $expected = new ExpectedWorkoutState($wrongVersion ? new WorkoutVersion(2) : $plan->version,
            $wrongRevision ? WorkoutRevisionId::generate() : $plan->currentRevisionId);
        $replacement = $this->revision($plan->id, 3, '2026-11-06T10:01:00Z');
        $this->expectException(WorkoutConflict::class);
        $plan->withRevision($replacement, $expected);
    }

    /** @return iterable<string,array{bool,bool}> */
    public static function wrongExpectations(): iterable
    {
        yield 'stale version only' => [true, false];
        yield 'different revision only' => [false, true];
        yield 'both stale' => [true, true];
    }

    public function testEqualPublicationTimesDoNotAllowAStaleWriterToWin(): void
    {
        $first = $this->revision(WorkoutId::generate());
        $plan = WorkoutPlan::create($first);
        $expected = $plan->expectation();
        $updated = $plan->withRevision($this->revision($plan->id, 3), $expected);
        self::assertTrue($updated->updatedAt->equals($plan->updatedAt));
        self::assertSame(2, $updated->version->value);
        $this->expectException(WorkoutConflict::class);
        $updated->withRevision($this->revision($plan->id, 4), $expected);
    }

    public function testAReappearingRevisionIdentityDoesNotReviveAnOldExpectedVersion(): void
    {
        $plan = WorkoutPlan::create($this->revision(WorkoutId::generate()));
        $observed = $plan->expectation();
        $later = new WorkoutPlan($plan->id, $plan->currentRevisionId, new WorkoutVersion(3), $plan->createdAt, $this->time('2026-11-06T10:02:00Z'));
        $this->expectException(WorkoutConflict::class);
        $later->assertExpected($observed);
    }

    #[DataProvider('invalidReplacements')]
    public function testAnotherPlanAReusedCurrentIdAndBackdatedPublicationsAreRejected(string $case): void
    {
        $plan = WorkoutPlan::create($this->revision(WorkoutId::generate()));
        $plan = $plan->withRevision($this->revision($plan->id, 3, '2026-11-06T10:02:00Z'), $plan->expectation());
        $replacement = match ($case) {
            'foreign plan' => $this->revision(WorkoutId::generate(), 4, '2026-11-06T10:03:00Z'),
            'current id reuse' => $this->revision($plan->id, 4, '2026-11-06T10:03:00Z', $plan->currentRevisionId),
            'before creation' => $this->revision($plan->id, 4, '2026-11-06T09:59:00Z'),
            'before latest publication' => $this->revision($plan->id, 4, '2026-11-06T10:01:00Z'),
            default => throw new \LogicException('Unknown replacement fixture.'),
        };
        $this->expectException(\InvalidArgumentException::class);
        $plan->withRevision($replacement, $plan->expectation());
    }

    /** @return iterable<string,array{string}> */
    public static function invalidReplacements(): iterable
    {
        foreach (['foreign plan', 'current id reuse', 'before creation', 'before latest publication'] as $case) {
            yield $case => [$case];
        }
    }

    public function testVersionExhaustionFailsWithoutReplacingTheOriginalHead(): void
    {
        $revision = $this->revision(WorkoutId::generate());
        $plan = new WorkoutPlan($revision->workoutId, $revision->id, new WorkoutVersion(PHP_INT_MAX), $revision->createdAt, $revision->createdAt);
        try {
            $plan->withRevision($this->revision($plan->id, 3), $plan->expectation());
            self::fail('Version overflow was accepted.');
        } catch (\OverflowException) {
            self::assertTrue($plan->reference()->equals($revision->reference()));
            self::assertSame(PHP_INT_MAX, $plan->version->value);
        }
    }

    public function testHydratedHeadCannotPredateItsOwnCreation(): void
    {
        $first = $this->revision(WorkoutId::generate());
        $this->expectException(\InvalidArgumentException::class);
        new WorkoutPlan($first->workoutId, $first->id, new WorkoutVersion(1), $first->createdAt, $this->time('2026-11-06T09:00:00Z'));
    }

    public function testReferencesCompareBothTypedIdsAndCanonicalUuidStringsRoundTrip(): void
    {
        $id = WorkoutId::generate();
        $revision = WorkoutRevisionId::generate();
        $reference = new WorkoutRevisionReference($id, $revision);
        self::assertTrue($reference->equals(new WorkoutRevisionReference(WorkoutId::fromString(strtoupper($id->toString())), WorkoutRevisionId::fromString($revision->toString()))));
        self::assertFalse($reference->equals(new WorkoutRevisionReference(WorkoutId::generate(), $revision)));
        self::assertFalse($reference->equals(new WorkoutRevisionReference($id, WorkoutRevisionId::generate())));
    }

    #[DataProvider('invalidVersions')]
    public function testZeroAndNegativeVersionsAreNotConcurrencyTokens(int $version): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WorkoutVersion($version);
    }

    /** @return iterable<string,array{int}> */
    public static function invalidVersions(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
