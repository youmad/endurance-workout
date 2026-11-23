<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Workout\Duration\DistanceDuration;
use Youmad\Endurance\Workout\Duration\OpenDuration;
use Youmad\Endurance\Workout\Duration\TimeDuration;
use Youmad\Endurance\Workout\Repeat;
use Youmad\Endurance\Workout\Sequence;
use Youmad\Endurance\Workout\Step;
use Youmad\Endurance\Workout\StepPurpose;
use Youmad\Endurance\Workout\Target\FreeTarget;
use Youmad\Endurance\Workout\Target\TargetMetric;
use Youmad\Endurance\Workout\Target\TargetRange;

final class PrescriptionTest extends TestCase
{
    public function testMixedDurationsAndRelativeTargetsRemainExplicitPrescriptions(): void
    {
        $target = new TargetRange(TargetMetric::PowerPercentFtp, 105, 120);
        $work = new Step(new TimeDuration(500_001), $target, StepPurpose::Active);
        $recover = new Step(new DistanceDuration(500.25), new TargetRange(TargetMetric::Power, 0, 0), StepPurpose::Recovery);
        $manual = new Step(new OpenDuration(), name: '<b>Finish manually</b>');
        $steps = new Sequence(new Repeat(2, new Sequence($work, $recover)), $manual);
        self::assertSame(5, $steps->occurrenceCount);
        self::assertSame($target, $steps->stepAt(3)->target);
        self::assertSame('%', $target->metric->unit());
        self::assertSame(105.0, $target->minimum);
        self::assertSame(120.0, $target->maximum);
        self::assertSame(500_001, $steps->stepAt(1)->duration->microseconds);
        self::assertSame(500.25, $steps->stepAt(2)->duration->metres);
        self::assertSame(0.0, $steps->stepAt(4)->target->minimum);
        self::assertInstanceOf(OpenDuration::class, $steps->stepAt(5)->duration);
        self::assertInstanceOf(FreeTarget::class, $steps->stepAt(5)->target);
        self::assertSame(StepPurpose::Unspecified, $steps->stepAt(5)->purpose);
        self::assertSame('<b>Finish manually</b>', $steps->stepAt(5)->name);
    }

    #[DataProvider('metrics')]
    public function testTargetUnitsDoNotDependOnARangeValueOrAnInventedAthleteBaseline(TargetMetric $metric, string $unit): void
    {
        self::assertSame($unit, new TargetRange($metric, 0, 125)->metric->unit());
    }

    /** @return iterable<string,array{TargetMetric,string}> */
    public static function metrics(): iterable
    {
        yield 'absolute power' => [TargetMetric::Power, 'W'];
        yield 'absolute heart rate' => [TargetMetric::HeartRate, 'bpm'];
        yield 'absolute cadence' => [TargetMetric::Cadence, 'rpm'];
        yield 'absolute speed' => [TargetMetric::Speed, 'm/s'];
        yield 'relative power' => [TargetMetric::PowerPercentFtp, '%'];
        yield 'maximum heart rate' => [TargetMetric::HeartRatePercentMaximum, '%'];
        yield 'threshold heart rate' => [TargetMetric::HeartRatePercentThreshold, '%'];
    }

    #[DataProvider('badRanges')]
    public function testNonFiniteNegativeOrReversedTargetsAreRejected(float $minimum, float $maximum): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TargetRange(TargetMetric::Power, $minimum, $maximum);
    }

    /** @return iterable<string,array{float,float}> */
    public static function badRanges(): iterable
    {
        yield 'negative' => [-1.0, 0.0];
        yield 'reversed' => [2.0, 1.0];
        yield 'unknown lower' => [NAN, 1.0];
        yield 'unknown upper' => [1.0, NAN];
        yield 'unbounded upper' => [0.0, INF];
        yield 'negative infinity' => [-INF, 0.0];
    }

    #[DataProvider('badDistances')]
    public function testMissingOrNonPositiveDistanceDoesNotBecomeManualCompletion(float $metres): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DistanceDuration($metres);
    }

    /** @return iterable<string,array{float}> */
    public static function badDistances(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-1.0];
        yield 'unknown' => [NAN];
        yield 'infinite' => [INF];
    }

    #[DataProvider('badTimes')]
    public function testNonPositiveTimeDoesNotBecomeManualCompletion(int $microseconds): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimeDuration($microseconds);
    }

    /** @return iterable<string,array{int}> */
    public static function badTimes(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
