<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            ...$validated,
            'api_token' => Str::random(80),
        ]);

        return response()->json([
            'user'  => UserResource::make($user),
            'token' => $user->api_token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (!Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            throw ValidationException::withMessages([
                'email' => ['invalid_credentials'],
            ]);
        }

        $user = Auth::user();
        $user->api_token = Str::random(80);
        $user->save();

        return response()->json([
            'user'  => UserResource::make($user),
            'token' => $user->api_token,
        ]);
    }
}
