<?php

use App\Services\StreakCalculator;
use Carbon\CarbonImmutable;

// Unit tests don't boot Laravel, so they never call now(): every date is explicit.
function daysBefore(CarbonImmutable $day, int $dayCount): string
{
    return $day->subDays($dayCount)->toDateString();
}

function streakReferenceDay(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-03-15');
}

it('returns zero when the habit was never completed', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $completedDates = [];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(0);
});

it('counts today when only today is completed', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $completedDates = [daysBefore($today, 0)];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(1);
});

it('keeps the streak alive from yesterday when today is not marked yet', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $completedDates = [daysBefore($today, 1)];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(1);
});

it('counts consecutive days ending today', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $completedDates = [daysBefore($today, 0), daysBefore($today, 1), daysBefore($today, 2)];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(3);
});

it('counts consecutive days ending yesterday', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $completedDates = [daysBefore($today, 1), daysBefore($today, 2)];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(2);
});

it('resets the streak when yesterday was missed', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $completedDates = [daysBefore($today, 2)];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(0);
});

it('stops counting at the first gap', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $olderRunDates = array_map(fn (int $dayCount) => daysBefore($today, $dayCount), [2, 3, 4, 5, 6]);
    $completedDates = [daysBefore($today, 0), ...$olderRunDates];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(1);
});

it('counts across a year boundary', function () {
    $calculator = new StreakCalculator;
    $newYearsDay = CarbonImmutable::parse('2026-01-01');
    $completedDates = [daysBefore($newYearsDay, 0), daysBefore($newYearsDay, 1), daysBefore($newYearsDay, 2)];

    $streak = $calculator->calculate($completedDates, $newYearsDay);

    expect($streak)->toBe(3);
});

it('ignores input order and duplicates', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $completedDates = [
        daysBefore($today, 2),
        daysBefore($today, 0),
        daysBefore($today, 1),
        daysBefore($today, 1),
    ];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(3);
});

it('ignores completions after today', function () {
    $calculator = new StreakCalculator;
    $today = streakReferenceDay();
    $tomorrow = $today->addDay()->toDateString();
    $completedDates = [$tomorrow, daysBefore($today, 0)];

    $streak = $calculator->calculate($completedDates, $today);

    expect($streak)->toBe(1);
});
