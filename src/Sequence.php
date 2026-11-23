<?php

declare(strict_types=1);

namespace Endurance\Workout;

/** Nonempty ordered prescription tree. Construction/counting never expands repetitions. */
final readonly class Sequence
{
    /** @var non-empty-list<Step|Repeat> */
    public array $nodes;
    public int $occurrenceCount;

    public function __construct(Step|Repeat ...$nodes)
    {
        if ([] === $nodes || !array_is_list($nodes)) {
            throw new \InvalidArgumentException('A sequence requires nonempty positional steps/repeats.');
        }
        $count = 0;
        foreach ($nodes as $node) {
            $size = $node instanceof Step ? 1 : $node->occurrenceCount;
            if ($count > PHP_INT_MAX - $size) {
                throw new \InvalidArgumentException('Expanded sequence count must fit a PHP integer.');
            }
            $count += $size;
        }
        $this->nodes = $nodes;
        $this->occurrenceCount = $count;
    }

    /** One-based occurrence, scoped to this exact immutable prescription. No preceding expansion. */
    public function stepAt(int $ordinal): Step
    {
        if ($ordinal < 1 || $ordinal > $this->occurrenceCount) {
            throw new \OutOfBoundsException('Workout occurrence is outside the sequence.');
        }
        $sequence = $this;
        while (true) {
            foreach ($sequence->nodes as $node) {
                $size = $node instanceof Step ? 1 : $node->occurrenceCount;
                if ($ordinal > $size) {
                    $ordinal -= $size;
                    continue;
                }
                if ($node instanceof Step) {
                    return $node;
                }
                $ordinal = ($ordinal - 1) % $node->steps->occurrenceCount + 1;
                $sequence = $node->steps;
                continue 2;
            }
            throw new \LogicException('Invalid immutable workout sequence count.');
        }
    }

    /**
     * Bounded page after a one-based occurrence ordinal. Seeking skips complete repeat subtrees.
     * Empty pages after the end are valid; overlapping/sequential readers share no cursor state.
     *
     * @return \Generator<int,Step> Occurrence ordinal => original immutable instruction
     */
    public function occurrences(int $after = 0, int $limit = 1000): \Generator
    {
        if ($after < 0 || $limit < 1) {
            throw new \InvalidArgumentException('Workout pages require a nonnegative offset and positive limit.');
        }
        $count = min($limit, max(0, $this->occurrenceCount - $after));
        for ($offset = 0; $offset < $count; ++$offset) {
            $ordinal = $after + $offset + 1;
            yield $ordinal => $this->stepAt($ordinal);
        }
    }
}
