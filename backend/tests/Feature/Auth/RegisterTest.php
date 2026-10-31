<?php

use App\Models\User;

function validRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'valid-password-123',
        'password_confirmation' => 'valid-password-123',
    ], $overrides);
}

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/register', validRegistrationPayload());

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'email'], 'token'])
        ->assertJsonPath('data.email', 'ada@example.com');
    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
});

it('rejects a duplicate email', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/register', validRegistrationPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects a missing password', function () {
    $this->postJson('/api/register', validRegistrationPayload(['password' => null, 'password_confirmation' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('rejects an unconfirmed password', function () {
    $this->postJson('/api/register', validRegistrationPayload(['password_confirmation' => 'something-else']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('rejects an email that only differs by casing', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $this->postJson('/api/register', validRegistrationPayload(['email' => 'Ada@Example.com']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('throttles repeated registrations from one IP', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/register', validRegistrationPayload(['email' => "user{$attempt}@example.com"]))->assertCreated();
    }

    $this->postJson('/api/register', validRegistrationPayload(['email' => 'user11@example.com']))
        ->assertTooManyRequests();
});

it('stores the email in lowercase', function () {
    $this->postJson('/api/register', validRegistrationPayload(['email' => 'Ada@Example.com']))->assertCreated();

    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
});
