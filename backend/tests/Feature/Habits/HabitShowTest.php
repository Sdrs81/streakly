<?php

use App\Models\Habit;
use App\Models\User;

it('shows the caller\'s habit', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson("/api/habits/{$habit->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $habit->id)
        ->assertJsonPath('data.name', $habit->name);
});

it('forbids viewing another user\'s habit', function () {
    $habit = Habit::factory()->create();

    $this->withToken(User::factory()->create()->createToken('test')->plainTextToken)
        ->getJson("/api/habits/{$habit->id}")
        ->assertForbidden();
});

it('rejects guests', function () {
    $habit = Habit::factory()->create();

    $this->getJson("/api/habits/{$habit->id}")->assertUnauthorized();
});
