<?php

use App\Models\User;

it('revokes the current token', function () {
    $user = User::factory()->create();
    $plainTextToken = $user->createToken('test')->plainTextToken;

    $this->withToken($plainTextToken)->postJson('/api/logout')->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);
    $this->app['auth']->forgetGuards();
    $this->withToken($plainTextToken)->getJson('/api/user')->assertUnauthorized();
});

it('rejects guests', function () {
    $this->postJson('/api/logout')->assertUnauthorized();
});
