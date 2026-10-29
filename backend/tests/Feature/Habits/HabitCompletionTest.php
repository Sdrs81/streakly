<?php

use App\Models\Habit;
use App\Models\HabitCompletion;
use App\Models\User;
use App\Support\HabitCalendar;
use Carbon\CarbonImmutable;

function completionToday(): string
{
    return HabitCalendar::today()->toDateString();
}

it('marks today as done and returns the updated habit', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->putJson("/api/habits/{$habit->id}/completions/today")
        ->assertOk()
        ->assertJsonPath('data.id', $habit->id)
        ->assertJsonPath('data.current_streak', 1)
        ->assertJsonPath('data.is_completed_today', true);

    $this->assertDatabaseHas('habit_completions', ['habit_id' => $habit->id, 'completed_on' => completionToday()]);
});

it('marking today is idempotent', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)->putJson("/api/habits/{$habit->id}/completions/today")->assertOk();
    $this->withToken($token)->putJson("/api/habits/{$habit->id}/completions/today")
        ->assertOk()
        ->assertJsonPath('data.current_streak', 1);

    expect($habit->completions()->count())->toBe(1);
});

it('unmarking today removes only today\'s completion', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();
    $today = HabitCalendar::today();
    HabitCompletion::factory()->for($habit)->create(['completed_on' => $today->toDateString()]);
    HabitCompletion::factory()->for($habit)->create(['completed_on' => $today->subDay()->toDateString()]);

    $this->withToken($user->createToken('test')->plainTextToken)
        ->deleteJson("/api/habits/{$habit->id}/completions/today")
        ->assertOk()
        ->assertJsonPath('data.current_streak', 1)
        ->assertJsonPath('data.is_completed_today', false);

    $this->assertDatabaseMissing('habit_completions', ['habit_id' => $habit->id, 'completed_on' => $today->toDateString()]);
    $this->assertDatabaseHas('habit_completions', ['habit_id' => $habit->id, 'completed_on' => $today->subDay()->toDateString()]);
});

it('unmarking an unmarked day is a no-op', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->deleteJson("/api/habits/{$habit->id}/completions/today")
        ->assertOk()
        ->assertJsonPath('data.is_completed_today', false)
        ->assertJsonPath('data.current_streak', 0);
});

it('uses the habit timezone to decide what today is', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();
    $token = $user->createToken('test')->plainTextToken;
    $beforeLocalMidnight = CarbonImmutable::parse('2026-03-15 20:30', 'UTC'); // 23:30 in Istanbul
    $afterLocalMidnight = CarbonImmutable::parse('2026-03-15 21:10', 'UTC'); // 00:10 next day in Istanbul

    $this->travelTo($beforeLocalMidnight);
    $this->withToken($token)->putJson("/api/habits/{$habit->id}/completions/today")->assertOk();

    $this->assertDatabaseHas('habit_completions', [
        'habit_id' => $habit->id,
        'completed_on' => $beforeLocalMidnight->setTimezone('Europe/Istanbul')->toDateString(),
    ]);

    $this->travelTo($afterLocalMidnight);
    $this->withToken($token)->getJson('/api/habits')
        ->assertOk()
        ->assertJsonPath('data.0.is_completed_today', false)
        ->assertJsonPath('data.0.current_streak', 1);
});

it('shows the streak and today flag in the habit list', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();
    $today = HabitCalendar::today();
    foreach ([0, 1, 2] as $dayCount) {
        HabitCompletion::factory()->for($habit)->create(['completed_on' => $today->subDays($dayCount)->toDateString()]);
    }

    $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson('/api/habits')
        ->assertOk()
        ->assertJsonPath('data.0.current_streak', 3)
        ->assertJsonPath('data.0.is_completed_today', true);
});

it('forbids marking another user\'s habit', function () {
    $habit = Habit::factory()->create();

    $this->withToken(User::factory()->create()->createToken('test')->plainTextToken)
        ->putJson("/api/habits/{$habit->id}/completions/today")
        ->assertForbidden();

    $this->assertDatabaseCount('habit_completions', 0);
});

it('forbids unmarking another user\'s habit', function () {
    $habit = Habit::factory()->create();
    HabitCompletion::factory()->for($habit)->create();

    $this->withToken(User::factory()->create()->createToken('test')->plainTextToken)
        ->deleteJson("/api/habits/{$habit->id}/completions/today")
        ->assertForbidden();

    $this->assertDatabaseCount('habit_completions', 1);
});

it('rejects guests', function () {
    $habit = Habit::factory()->create();

    $this->putJson("/api/habits/{$habit->id}/completions/today")->assertUnauthorized();
    $this->deleteJson("/api/habits/{$habit->id}/completions/today")->assertUnauthorized();
});

it('deleting a habit removes its completions', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();
    HabitCompletion::factory()->for($habit)->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->deleteJson("/api/habits/{$habit->id}")
        ->assertNoContent();

    $this->assertDatabaseCount('habit_completions', 0);
});
