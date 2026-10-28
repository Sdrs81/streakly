<?php

use App\Models\User;

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('test')->plainTextToken)
        ->getJson('/api/user')
        ->assertOk()
        ->assertExactJson(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
});

it('rejects guests', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('rejects a token after it expires', function () {
    $user = User::factory()->create();
    $plainTextToken = $user->createToken('test')->plainTextToken;
    $expirationMinutes = config('sanctum.expiration');

    $this->travel($expirationMinutes + 1)->minutes();
    $this->withToken($plainTextToken)->getJson('/api/user')->assertUnauthorized();
});
