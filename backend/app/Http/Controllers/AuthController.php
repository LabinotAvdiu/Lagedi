<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'email'      => 'required|string|email|max:255|unique:users',
            'password'   => 'required|string|min:8',
            'phone'      => 'required|string|max:20',
        ]);

        $user = User::create([
            'name'       => $validated['name'],
            'first_name' => $validated['first_name'] ?? null,
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'phone'      => $validated['phone'],
            'api_token'  => Str::random(80),
        ]);

        return response()->json([
            'user'  => $user->only(['id', 'name', 'first_name', 'email', 'phone']),
            'token' => $user->api_token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        $user = Auth::user();
        $user->api_token = Str::random(80);
        $user->save();

        return response()->json([
            'user'  => $user->only(['id', 'name', 'first_name', 'email', 'phone']),
            'token' => $user->api_token,
        ]);
    }
}
