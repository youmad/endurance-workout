# Endurance Workout

Immutable workout prescriptions, bounded repeat traversal and versioned plans
for PHP 8.5.

```bash
composer require youmad/endurance-workout
```

```php
use Youmad\Endurance\Workout\{Repeat, Sequence, Step, StepPurpose, Workout};
use Youmad\Endurance\Workout\Duration\TimeDuration;

$work = new Step(new TimeDuration(120_000_000));
$recovery = new Step(new TimeDuration(60_000_000), purpose: StepPurpose::Recovery);
$workout = new Workout(new Sequence(new Repeat(3, new Sequence($work, $recovery))));
$fifth = $workout->steps->stepAt(5);
```

- Time durations use integer microseconds; distance durations use metres.
  `OpenDuration` means explicit manual completion, not an unknown duration.
  Relative targets retain their declared baseline without selecting athlete data.
- Occurrences are one-based within the exact prescription. `stepAt()` and bounded
  `occurrences(after:, limit:)` skip repeat subtrees without expanding them.
- `PublishWorkout` takes caller-generated plan/revision IDs, a clock and a
  `WorkoutRepository`. Replacement checks both expected head fields atomically;
  historical content and revision IDs remain immutable.
- Storage is unscoped; authorize access in your application. Reconcile uncertain
  writes by durable identity before retrying.

[Executable example](examples/intervals.php).

License: [MPL-2.0](LICENSE).
