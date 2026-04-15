<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // POST /auth/register
    public function register(RegisterRequest $request): AuthResource|JsonResponse
    {
        $data = $request->validated();
        $role = $data['role'] ?? 'user';

        $user = DB::transaction(function () use ($data, $role): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'phone'      => $data['phone'] ?? null,
                'city'       => $data['city'] ?? null,
                'role'       => $role,
            ]);

            if ($role === UserRole::Company->value) {
                $company = Company::create([
                    'name'    => $data['company_name'],
                    'address' => $data['address'],
                    'city'    => $data['city'] ?? '',
                ]);

                // Attach the user as owner in the company_user pivot
                $company->users()->attach($user->id, [
                    'role'      => 'owner',
                    'is_active' => true,
                ]);
            }

            return $user;
        });

        $user->sendEmailVerificationNotification();

        [$accessToken, $refreshToken] = $this->issueTokenPair($user);

        return (new AuthResource($user, $accessToken, $refreshToken))
            ->response()
            ->setStatusCode(201);
    }

    // POST /auth/login
    public function login(LoginRequest $request): AuthResource|JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'errors' => ['email' => ['invalid_credentials']],
            ], 401);
        }

        if ($user->isLocked()) {
            return response()->json([
                'errors' => ['email' => ['account_locked']],
            ], 423);
        }

        if (! Hash::check($data['password'], $user->password)) {
            $user->incrementFailedAttempts();

            return response()->json([
                'errors' => ['email' => ['invalid_credentials']],
            ], 401);
        }

        $user->clearFailedAttempts();

        [$accessToken, $refreshToken] = $this->issueTokenPair($user);

        return new AuthResource($user, $accessToken, $refreshToken);
    }

    // POST /auth/refresh
    public function refresh(RefreshTokenRequest $request): AuthResource|JsonResponse
    {
        $raw = $request->input('refresh_token');
        $hashed = hash('sha256', $raw);

        $refreshToken = RefreshToken::where('token', $hashed)
            ->with('user')
            ->first();

        if (! $refreshToken || ! $refreshToken->isValid()) {
            return response()->json([
                'errors' => ['refresh_token' => ['invalid_or_expired']],
            ], 401);
        }

        $user = $refreshToken->user;

        $refreshToken->revoke();
        if ($refreshToken->access_token_id) {
            $user->tokens()->where('id', $refreshToken->access_token_id)->delete();
        }

        [$accessToken, $newRefreshToken] = $this->issueTokenPair($user);

        return new AuthResource($user, $accessToken, $newRefreshToken);
    }

    // POST /auth/logout
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        $request->user()->refreshTokens()->whereNull('revoked_at')->update([
            'revoked_at' => now(),
        ]);

        return response()->json(null, 204);
    }

    // GET /auth/profile
    public function profile(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    // PUT /auth/profile
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user()->fresh());
    }

    // POST /auth/google
    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        // TODO: Verify id_token with Google
        return response()->json([
            'errors' => ['provider' => ['not_configured']],
        ], 501);
    }

    // POST /auth/facebook
    public function facebookLogin(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        // TODO: Verify with Facebook Graph API
        return response()->json([
            'errors' => ['provider' => ['not_configured']],
        ], 501);
    }

    // POST /auth/forgot-password
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        // Always 200 to avoid email enumeration
        return response()->json(null, 200);
    }

    // POST /auth/reset-password
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'              => Hash::make($password),
                    'failed_login_attempts' => 0,
                    'locked_until'          => null,
                ])->save();

                $user->tokens()->delete();
                $user->refreshTokens()->update(['revoked_at' => now()]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'errors' => ['token' => ['invalid_or_expired']],
            ], 422);
        }

        return response()->json(null, 200);
    }

    // POST /auth/verify-email
    public function verifyEmail(VerifyEmailRequest $request): UserResource|JsonResponse
    {
        $data = $request->validated();

        $record = DB::table('email_verification_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $record || ! Hash::check($data['token'], $record->token)) {
            return response()->json([
                'errors' => ['token' => ['invalid_or_expired']],
            ], 422);
        }

        if (now()->gt($record->expires_at)) {
            return response()->json([
                'errors' => ['token' => ['expired']],
            ], 422);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'errors' => ['email' => ['not_found']],
            ], 404);
        }

        $user->markEmailAsVerified();
        DB::table('email_verification_tokens')->where('email', $data['email'])->delete();

        return new UserResource($user);
    }

    // POST /auth/resend-verification
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Always 200 to avoid email enumeration
        return response()->json(null, 200);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function issueTokenPair(User $user): array
    {
        $accessTokenModel = $user->createToken(
            'access_token',
            ['*'],
            now()->addHours(24) // TODO: reduce to 15 minutes in production
        );
        $plainAccessToken = $accessTokenModel->plainTextToken;

        $plainRefreshToken = Str::random(64);
        $hashedRefreshToken = hash('sha256', $plainRefreshToken);

        RefreshToken::create([
            'user_id'         => $user->id,
            'token'           => $hashedRefreshToken,
            'access_token_id' => $accessTokenModel->accessToken->id,
            'expires_at'      => now()->addDays(90),
        ]);

        return [$plainAccessToken, $plainRefreshToken];
    }
}
