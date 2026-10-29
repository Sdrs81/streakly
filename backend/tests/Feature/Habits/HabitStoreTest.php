<?php

use App\Models\Habit;
use App\Models\User;

it('creates a habit for the caller', function () {
    $user = User::factory()->create();

    $response = $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/habits', ['name' => 'Read', 'description' => 'Twenty pages']);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Read')
        ->assertJsonPath('data.description', 'Twenty pages')
        ->assertJsonStructure(['data' => ['id', 'name', 'description', 'created_at']]);
    $this->assertDatabaseHas('habits', ['name' => 'Read', 'user_id' => $user->id]);
});

it('creates a habit without a description', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/habits', ['name' => 'Stretch'])
        ->assertCreated()
        ->assertJsonPath('data.description', null);
});

it('ignores a user_id sent in the payload', function () {
    $caller = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->withToken($caller->createToken('test')->plainTextToken)
        ->postJson('/api/habits', ['name' => 'Read', 'user_id' => $otherUser->id])
        ->assertCreated();

    expect(Habit::sole()->user_id)->toBe($caller->id);
});

it('rejects an invalid payload', function (array $payload, string $invalidField) {
    $user = User::factory()->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->postJson('/api/habits', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($invalidField);
})->with([
    'missing name' => [[], 'name'],
    'name too long' => [['name' => str_repeat('a', 101)], 'name'],
    'description too long' => [['name' => 'Read', 'description' => str_repeat('a', 501)], 'description'],
]);

it('rejects guests', function () {
    $this->postJson('/api/habits', ['name' => 'Read'])->assertUnauthorized();
});
