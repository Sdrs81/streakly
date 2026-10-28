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

it('rejects an unknown email', function () {
    $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'correct-horse-battery'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('throttles repeated login attempts for the same email', function () {
    $credentials = ['email' => 'nobody@example.com', 'password' => 'wrong-password'];

    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/login', $credentials)->assertUnprocessable();
    }

    $this->postJson('/api/login', $credentials)->assertTooManyRequests();
});

it('does not let failed logins block registration', function () {
    foreach (range(1, 11) as $attempt) {
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);
    }

    $this->postJson('/api/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ])->assertCreated();
});
