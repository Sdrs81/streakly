<?php

use App\Models\Habit;
use App\Models\User;

it('lists only the caller\'s habits, oldest first', function () {
    $user = User::factory()->create();
    $newerHabit = Habit::factory()->for($user)->create(['created_at' => now()->subDay()]);
    $olderHabit = Habit::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
    Habit::factory()->create();

    $response = $this->withToken($user->createToken('test')->plainTextToken)->getJson('/api/habits');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $olderHabit->id)
        ->assertJsonPath('data.1.id', $newerHabit->id);
});

it('returns an empty list when the caller has no habits', function () {
    $user = User::factory()->create();
    Habit::factory()->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson('/api/habits')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('rejects guests', function () {
    $this->getJson('/api/habits')->assertUnauthorized();
});
