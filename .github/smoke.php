<?php

declare(strict_types=1);

/** @param Closure(bool): void $ensure */
return static function (Closure $ensure, string $autoload, string $package): void {
    $argv[1] = $autoload;
    require $package.'/examples/intervals.php';
};
