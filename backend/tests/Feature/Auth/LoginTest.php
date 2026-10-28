<?php

use App\Models\User;

it('returns a token for valid credentials', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token'])
        ->assertJsonPath('data.id', $user->id);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create(['password' => 'correct-horse-battery']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects a missing payload', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
