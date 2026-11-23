<?php

declare(strict_types=1);

use Endurance\Foundation\ValueObject\Instant;
use Endurance\Workout\Duration\OpenDuration;
use Endurance\Workout\Duration\TimeDuration;
use Endurance\Workout\Entity\WorkoutPlan;
use Endurance\Workout\Entity\WorkoutRevision;
use Endurance\Workout\Repeat;
use Endurance\Workout\Sequence;
use Endurance\Workout\Step;
use Endurance\Workout\StepPurpose;
use Endurance\Workout\Target\TargetMetric;
use Endurance\Workout\Target\TargetRange;
use Endurance\Workout\ValueObject\WorkoutId;
use Endurance\Workout\ValueObject\WorkoutRevisionId;
use Endurance\Workout\Workout;

require $argv[1] ?? dirname(__DIR__).'/vendor/autoload.php';
$work = new Step(new TimeDuration(120_000_000), new TargetRange(TargetMetric::PowerPercentFtp, 105, 115), StepPurpose::Active, 'Work');
$recovery = new Step(new TimeDuration(60_000_000), purpose: StepPurpose::Recovery, name: 'Recovery');
$workout = new Workout(new Sequence(
    new Step(new OpenDuration(), purpose: StepPurpose::WarmUp, name: 'Warm up'),
    new Repeat(3, new Sequence($work, $recovery)),
    new Step(new OpenDuration(), purpose: StepPurpose::CoolDown, name: 'Cool down'),
), 'Example intervals');
if (8 !== $workout->steps->occurrenceCount || $workout->steps->stepAt(6) !== $work) {
    throw new RuntimeException('Workout occurrence ordering changed.');
}
foreach ($workout->steps->occurrences(limit: 8) as $ordinal => $step) {
    printf("%d. %s\n", $ordinal, $step->name);
}

$published = Instant::fromDateTimeImmutable(new DateTimeImmutable('2026-11-06T00:00:00Z'));
$original = new WorkoutRevision(WorkoutRevisionId::generate(), WorkoutId::generate(), $workout, $published);
$plan = WorkoutPlan::create($original);
$replacement = new WorkoutRevision(WorkoutRevisionId::generate(), $plan->id, new Workout(new Sequence($work), 'Shorter prescription'), $published);
$updated = $plan->withRevision($replacement, $plan->expectation());
if (8 !== $original->prescription->steps->occurrenceCount || 1 !== $replacement->prescription->steps->occurrenceCount
    || 2 !== $updated->version->value || $updated->reference()->equals($original->reference())) {
    throw new RuntimeException('New workout revision changed retained history.');
}
printf("Original prescription: %d occurrences; current revision: %d occurrences.\n", $original->prescription->steps->occurrenceCount, $replacement->prescription->steps->occurrenceCount);
