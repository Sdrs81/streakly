<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class StreakCalculator
{
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * @param  iterable<string>  $completedDates  `Y-m-d` strings, in any order
     */
    public function calculate(iterable $completedDates, CarbonImmutable $today): int
    {
        $completedDateSet = [];
        foreach ($completedDates as $completedDate) {
            $completedDateSet[$completedDate] = true;
        }

        // A streak stays alive until the end of today, so an unmarked today doesn't break it.
        $dayToCheck = isset($completedDateSet[$today->format(self::DATE_FORMAT)])
            ? $today
            : $today->subDay();

        $streakLength = 0;
        while (isset($completedDateSet[$dayToCheck->format(self::DATE_FORMAT)])) {
            $streakLength++;
            $dayToCheck = $dayToCheck->subDay();
        }

        return $streakLength;
    }
}
