<?php

declare(strict_types=1);

namespace Youmad\Endurance\Workout;

enum StepPurpose: string
{
    case Unspecified = 'unspecified';
    case WarmUp = 'warm_up';
    case Active = 'active';
    case Recovery = 'recovery';
    case Rest = 'rest';
    case CoolDown = 'cool_down';
}
