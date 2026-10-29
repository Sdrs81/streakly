<?php

use App\Services\StreakCalculator;
use Carbon\CarbonImmutable;

// Unit tests don't boot Laravel, so they never call now(): every date is explicit.
function daysBefore(CarbonImmutable $day, int $dayCount): string
{
    return $day->subDays($dayCount)->toDateString();
}

beforeEach(function () {
    $this->calculator = new StreakCalculator;
    $this->today = CarbonImmutable::parse('2026-03-15');
});

it('returns zero when the habit was never completed', function () {
    $completedDates = [];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(0);
});

it('counts today when only today is completed', function () {
    $completedDates = [daysBefore($this->today, 0)];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(1);
});

it('keeps the streak alive from yesterday when today is not marked yet', function () {
    $completedDates = [daysBefore($this->today, 1)];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(1);
});

it('counts consecutive days ending today', function () {
    $completedDates = [daysBefore($this->today, 0), daysBefore($this->today, 1), daysBefore($this->today, 2)];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(3);
});

it('counts consecutive days ending yesterday', function () {
    $completedDates = [daysBefore($this->today, 1), daysBefore($this->today, 2)];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(2);
});

it('resets the streak when yesterday was missed', function () {
    $completedDates = [daysBefore($this->today, 2)];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(0);
});

it('stops counting at the first gap', function () {
    $olderRunDates = array_map(fn (int $dayCount) => daysBefore($this->today, $dayCount), [2, 3, 4, 5, 6]);
    $completedDates = [daysBefore($this->today, 0), ...$olderRunDates];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(1);
});

it('counts across a year boundary', function () {
    $newYearsDay = CarbonImmutable::parse('2026-01-01');
    $completedDates = [daysBefore($newYearsDay, 0), daysBefore($newYearsDay, 1), daysBefore($newYearsDay, 2)];

    $streak = $this->calculator->calculate($completedDates, $newYearsDay);

    expect($streak)->toBe(3);
});

it('ignores input order and duplicates', function () {
    $completedDates = [
        daysBefore($this->today, 2),
        daysBefore($this->today, 0),
        daysBefore($this->today, 1),
        daysBefore($this->today, 1),
    ];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(3);
});

it('ignores completions after today', function () {
    $tomorrow = $this->today->addDay()->toDateString();
    $completedDates = [$tomorrow, daysBefore($this->today, 0)];

    $streak = $this->calculator->calculate($completedDates, $this->today);

    expect($streak)->toBe(1);
});
