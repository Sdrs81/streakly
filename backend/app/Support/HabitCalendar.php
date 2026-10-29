<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class HabitCalendar
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('streakly.timezone'))->startOfDay();
    }
}
