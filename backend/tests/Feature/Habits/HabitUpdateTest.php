<?php

use App\Models\Habit;
use App\Models\User;

it('updates the caller\'s habit', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->putJson("/api/habits/{$habit->id}", ['name' => 'Run', 'description' => null])
        ->assertOk()
        ->assertJsonPath('data.name', 'Run')
        ->assertJsonPath('data.description', null);
    $this->assertDatabaseHas('habits', ['id' => $habit->id, 'name' => 'Run', 'description' => null]);
});

it('patches only the fields that are sent', function () {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create(['name' => 'Read']);

    $this->withToken($user->createToken('test')->plainTextToken)
        ->patchJson("/api/habits/{$habit->id}", ['description' => 'Twenty pages'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Read')
        ->assertJsonPath('data.description', 'Twenty pages');
});

it('forbids updating another user\'s habit and leaves it unchanged', function () {
    $habit = Habit::factory()->create(['name' => 'Original']);

    $this->withToken(User::factory()->create()->createToken('test')->plainTextToken)
        ->putJson("/api/habits/{$habit->id}", ['name' => 'Hijacked'])
        ->assertForbidden();

    $this->assertDatabaseHas('habits', ['id' => $habit->id, 'name' => 'Original']);
});

it('answers 403 before 422 for another user\'s habit', function () {
    $habit = Habit::factory()->create();

    $this->withToken(User::factory()->create()->createToken('test')->plainTextToken)
        ->putJson("/api/habits/{$habit->id}", [])
        ->assertForbidden();
});

it('rejects an invalid payload', function (array $payload, string $invalidField) {
    $user = User::factory()->create();
    $habit = Habit::factory()->for($user)->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->putJson("/api/habits/{$habit->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidField);
})->with([
    'missing name' => [[], 'name'],
    'name too long' => [['name' => str_repeat('a', Habit::NAME_MAX_LENGTH + 1)], 'name'],
    'description too long' => [['name' => 'Read', 'description' => str_repeat('a', Habit::DESCRIPTION_MAX_LENGTH + 1)], 'description'],
]);

it('rejects guests', function () {
    $habit = Habit::factory()->create();

    $this->putJson("/api/habits/{$habit->id}", ['name' => 'Run'])->assertUnauthorized();
});
