<?php

declare(strict_types=1);

namespace Endurance\Workout\Target;

/** Relative metrics retain their declared reference; no athlete baseline is selected here. */
enum TargetMetric: string
{
    case Power = 'power';
    case HeartRate = 'heart_rate';
    case Cadence = 'cadence';
    case Speed = 'speed';
    case PowerPercentFtp = 'power_percent_ftp';
    case HeartRatePercentMaximum = 'heart_rate_percent_maximum';
    case HeartRatePercentThreshold = 'heart_rate_percent_threshold';

    public function unit(): string
    {
        return match ($this) {
            self::Power => 'W', self::HeartRate => 'bpm', self::Cadence => 'rpm', self::Speed => 'm/s',
            self::PowerPercentFtp, self::HeartRatePercentMaximum, self::HeartRatePercentThreshold => '%',
        };
    }
}
