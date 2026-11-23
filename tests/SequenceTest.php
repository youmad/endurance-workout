<?php

declare(strict_types=1);

namespace Endurance\Workout\Tests;

use Endurance\Workout\Duration\OpenDuration;
use Endurance\Workout\Duration\TimeDuration;
use Endurance\Workout\Repeat;
use Endurance\Workout\Sequence;
use Endurance\Workout\Step;
use Endurance\Workout\Workout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SequenceTest extends TestCase
{
    #[DataProvider('repetitionPairs')]
    public function testIndexedReadsAgreeWithSmallExplicitExpansions(int $outer, int $inner): void
    {
        $a = new Step(new OpenDuration(), name: 'a');
        $b = new Step(new OpenDuration(), name: 'b');
        $c = new Step(new OpenDuration(), name: 'c');
        $tail = new Step(new OpenDuration(), name: 'tail');
        $steps = new Sequence($a, new Repeat($outer, new Sequence(new Repeat($inner, new Sequence($b, $c)), $a)), $tail);
        $expected = [1 => $a];
        for ($i = 0; $i < $outer; ++$i) {
            for ($j = 0; $j < $inner; ++$j) {
                $expected[] = $b;
                $expected[] = $c;
            }
            $expected[] = $a;
        }
        $expected[] = $tail;
        self::assertSame(count($expected), $steps->occurrenceCount);
        foreach ($expected as $ordinal => $step) {
            self::assertSame($step, $steps->stepAt($ordinal));
        }
        for ($after = 0; $after <= count($expected) + 1; ++$after) {
            foreach ([1, 2, 7] as $limit) {
                self::assertSame(array_slice($expected, $after, $limit, true), iterator_to_array($steps->occurrences($after, $limit)));
            }
        }
    }

    /** @return iterable<string,array{int,int}> */
    public static function repetitionPairs(): iterable
    {
        foreach ([1, 2, 3] as $outer) {
            foreach ([1, 2, 3] as $inner) {
                yield $outer.' x '.$inner => [$outer, $inner];
            }
        }
    }

    public function testNestedRepeatsPreserveOrderAndOccurrencePositionsAcrossPages(): void
    {
        $warm = new Step(new TimeDuration(1_000_000), name: 'warm');
        $work = new Step(new TimeDuration(2_000_000), name: 'work');
        $recover = new Step(new OpenDuration(), name: 'recover');
        $cool = new Step(new TimeDuration(3_000_000), name: 'cool');
        $steps = new Sequence($warm, new Repeat(2, new Sequence($work, new Repeat(2, new Sequence($recover)))), $cool);
        $prescription = new Workout($steps, 'Intervals');
        self::assertSame(8, $prescription->steps->occurrenceCount);
        self::assertSame([$warm, $work, $recover, $recover, $work, $recover, $recover, $cool], array_values(iterator_to_array($steps->occurrences())));
        self::assertSame([3 => $recover, 4 => $recover, 5 => $work], iterator_to_array($steps->occurrences(after: 2, limit: 3)));
        self::assertSame([6 => $recover, 7 => $recover, 8 => $cool], iterator_to_array($steps->occurrences(after: 5, limit: 10)));
        self::assertSame([], iterator_to_array($steps->occurrences(after: 8)));
        self::assertSame([], iterator_to_array($steps->occurrences(after: PHP_INT_MAX)));
        foreach ([$warm, $work, $recover, $recover, $work, $recover, $recover, $cool] as $offset => $step) {
            self::assertSame($step, $steps->stepAt($offset + 1));
        }
    }

    public function testLargeRepeatCanBeReadAtTheEndWithoutExpandingOrOverflowingAnOrdinal(): void
    {
        $work = new Step(new TimeDuration(1));
        $before = memory_get_usage();
        $repeat = new Repeat(PHP_INT_MAX, new Sequence($work));
        $steps = new Sequence($repeat);
        self::assertSame(PHP_INT_MAX, $steps->occurrenceCount);
        self::assertSame($work, $steps->stepAt(PHP_INT_MAX));
        self::assertSame([PHP_INT_MAX - 1 => $work, PHP_INT_MAX => $work], iterator_to_array($steps->occurrences(after: PHP_INT_MAX - 2, limit: 10)));
        self::assertSame([1 => $work], iterator_to_array($steps->occurrences(limit: 1)));
        self::assertLessThan(1024 * 1024, memory_get_usage() - $before);
    }

    public function testDeepPlansAndConcurrentPagesHaveNoRecursiveTraversalOrSharedCursor(): void
    {
        $step = new Step(new OpenDuration());
        $steps = new Sequence($step);
        for ($i = 0; $i < 2000; ++$i) {
            $steps = new Sequence(new Repeat(1, $steps));
        }
        self::assertSame($step, $steps->stepAt(1));
        $a = $steps->occurrences(limit: 1);
        $b = $steps->occurrences(limit: 1);
        self::assertSame($step, $a->current());
        self::assertSame([1 => $step], iterator_to_array($b));
        self::assertSame(1, $a->key());
        $a->next();
        self::assertFalse($a->valid());
    }

    public function testOneRepetitionAndSharedInstructionsKeepTheirDeclaredStructure(): void
    {
        $step = new Step(new OpenDuration());
        $body = new Sequence($step);
        $repeat = new Repeat(1, $body);
        $first = new Sequence($repeat, $step);
        $second = new Sequence($step, $repeat);
        self::assertSame($repeat, $first->nodes[0]);
        self::assertSame($body, $second->nodes[1]->steps);
        self::assertSame([1 => $step, 2 => $step], iterator_to_array($first->occurrences()));
        self::assertSame([1 => $step, 2 => $step], iterator_to_array($second->occurrences()));
    }

    #[DataProvider('invalidPlans')]
    public function testInvalidOrUnrepresentableStructuresFailBeforeAnyExpansion(string $case): void
    {
        $step = new Step(new OpenDuration());
        $this->expectException(\InvalidArgumentException::class);
        match ($case) {
            'empty' => new Sequence(),
            'named children' => new Sequence(named: $step),
            'zero repeat' => new Repeat(0, new Sequence($step)),
            'negative repeat' => new Repeat(-1, new Sequence($step)),
            'product overflow' => new Repeat(PHP_INT_MAX, new Sequence($step, $step)),
            'sum overflow' => new Sequence(new Repeat(PHP_INT_MAX, new Sequence($step)), $step),
            default => throw new \LogicException('Unknown invalid-plan fixture.'),
        };
    }

    /** @return iterable<string,array{string}> */
    public static function invalidPlans(): iterable
    {
        foreach (['empty', 'named children', 'zero repeat', 'negative repeat', 'product overflow', 'sum overflow'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('invalidOrdinals')]
    public function testOccurrenceLookupRejectsPositionsOutsideThisExactPlan(int $ordinal): void
    {
        $steps = new Sequence(new Step(new OpenDuration()));
        $this->expectException(\OutOfBoundsException::class);
        $steps->stepAt($ordinal);
    }

    /** @return iterable<string,array{int}> */
    public static function invalidOrdinals(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'after end' => [2];
        yield 'large' => [PHP_INT_MAX];
    }

    #[DataProvider('invalidPages')]
    public function testBadPageBoundsAreNotAcceptedAsAnEmptyOrUnboundedRead(int $after, int $limit): void
    {
        $steps = new Sequence(new Step(new OpenDuration()));
        $this->expectException(\InvalidArgumentException::class);
        iterator_to_array($steps->occurrences($after, $limit));
    }

    /** @return iterable<string,array{int,int}> */
    public static function invalidPages(): iterable
    {
        yield 'negative cursor' => [-1, 1];
        yield 'zero limit' => [0, 0];
        yield 'negative limit' => [0, -1];
    }
}
