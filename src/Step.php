<?php

declare(strict_types=1);

namespace Endurance\Workout;

use Endurance\Workout\Duration\DistanceDuration;
use Endurance\Workout\Duration\OpenDuration;
use Endurance\Workout\Duration\TimeDuration;
use Endurance\Workout\Target\FreeTarget;
use Endurance\Workout\Target\TargetRange;

final readonly class Step
{
    public function __construct(
        public TimeDuration|DistanceDuration|OpenDuration $duration,
        public FreeTarget|TargetRange $target = new FreeTarget(),
        public StepPurpose $purpose = StepPurpose::Unspecified,
        public ?string $name = null,
        public ?string $instructions = null,
    ) {
    }
}
