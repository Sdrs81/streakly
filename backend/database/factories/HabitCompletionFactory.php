<?php

namespace Database\Factories;

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Support\HabitCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitCompletion>
 */
class HabitCompletionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'habit_id' => Habit::factory(),
            'completed_on' => HabitCalendar::today()->toDateString(),
        ];
    }
}
