<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout;

use Youmad\Endurance\Workout\Duration\DistanceDuration;
use Youmad\Endurance\Workout\Duration\OpenDuration;
use Youmad\Endurance\Workout\Duration\TimeDuration;
use Youmad\Endurance\Workout\Target\FreeTarget;
use Youmad\Endurance\Workout\Target\TargetRange;

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
