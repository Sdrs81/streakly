<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->authenticated($user)->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        $hasValidCredentials = $user
            ? $provider->validateCredentials($user, $credentials)
            : $this->spendHashingTime($credentials['password']);

        if (! $hasValidCredentials) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $this->authenticated($user);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    // Without this, unknown emails answer faster than wrong passwords and reveal which accounts exist.
    private function spendHashingTime(string $password): bool
    {
        Hash::make($password);

        return false;
    }

    private function authenticated(User $user): JsonResponse
    {
        return UserResource::make($user)
            ->additional(['token' => $user->createToken('auth')->plainTextToken])
            ->response();
    }
}
