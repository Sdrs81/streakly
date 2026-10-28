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
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($request->validated());

        if (! $user || ! $provider->validateCredentials($user, $request->validated())) {
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

    private function authenticated(User $user): JsonResponse
    {
        return UserResource::make($user)
            ->additional(['token' => $user->createToken('auth')->plainTextToken])
            ->response();
    }
}
