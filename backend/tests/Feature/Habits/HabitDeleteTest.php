<?php

use App\Models\Habit;
use App\Models\User;

it('deletes the caller\'s habit', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->deleteJson("/api/habits/{$habit->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('habits', ['id' => $habit->id]);
});

it('forbids deleting another user\'s habit and keeps it', function () {
    $habit = Habit::factory()->create();

    $this->withToken(User::factory()->create()->createToken('test')->plainTextToken)
        ->deleteJson("/api/habits/{$habit->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('habits', ['id' => $habit->id]);
});

it('rejects guests', function () {
    $habit = Habit::factory()->create();

    $this->deleteJson("/api/habits/{$habit->id}")->assertUnauthorized();
    $this->assertDatabaseHas('habits', ['id' => $habit->id]);
});
