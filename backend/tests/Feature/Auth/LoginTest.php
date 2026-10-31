<?php

use App\Models\User;

it('returns a token for valid credentials', function () {
    $user = User::factory()->create(['password' => 'valid-password-123']);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'valid-password-123'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token'])
        ->assertJsonPath('data.id', $user->id);
});

it('rejects a wrong password', function () {
    $user = User::factory()->create(['password' => 'valid-password-123']);

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
    $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'valid-password-123'])
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
        'password' => 'valid-password-123',
        'password_confirmation' => 'valid-password-123',
    ])->assertCreated();
});

it('throttles password spraying across different emails from one IP', function () {
    foreach (range(1, 30) as $attempt) {
        $this->postJson('/api/login', ['email' => "user{$attempt}@example.com", 'password' => 'wrong-password'])
            ->assertUnprocessable();
    }

    $this->postJson('/api/login', ['email' => 'blocked@example.com', 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

it('rejects a non-string email with a validation error', function () {
    $this->postJson('/api/login', ['email' => ['nobody@example.com'], 'password' => 'valid-password-123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('logs in regardless of email casing', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => 'valid-password-123']);

    $this->postJson('/api/login', ['email' => ' Ada@Example.com ', 'password' => 'valid-password-123'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('counts padded emails towards the same throttle bucket', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertUnprocessable();
    }

    $this->postJson('/api/login', ['email' => ' Nobody@Example.com ', 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

it('rejects an oversized password', function () {
    $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});
