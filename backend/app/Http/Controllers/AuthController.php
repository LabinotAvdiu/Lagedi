<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\CompanyRole;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Jobs\SendAppointmentCancelledByClientNotification;
use App\Jobs\SendInvitationDecisionPush;
use App\Jobs\SendWelcomeClientEmail;
use App\Jobs\SendWelcomeOwnerEmail;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // POST /auth/register
    public function register(RegisterRequest $request): AuthResource|JsonResponse
    {
        $data = $request->validated();
        $role = $data['role'] ?? 'user';

        // --- Invitation-token branch (pre-flight validation) -----------------
        $invitation = null;
        if (! empty($data['invitation_token'])) {
            $rawToken   = $data['invitation_token'];
            $invitation = EmployeeInvitation::where('token_hash', hash('sha256', $rawToken))
                ->with('company')
                ->first();

            if (! $invitation || $invitation->status !== InvitationStatus::Pending) {
                return response()->json(['message' => 'Invitation no longer valid.'], 410);
            }

            if ($invitation->expires_at->isPast()) {
                return response()->json(['message' => 'Invitation expired.'], 410);
            }

            if (strtolower($data['email']) !== strtolower($invitation->email)) {
                return response()->json([
                    'message' => 'Email does not match invitation.',
                    'errors'  => ['email' => ['email-mismatch']],
                ], 422);
            }
        }
        // ---------------------------------------------------------------------

        $user = DB::transaction(function () use ($data, $role, $invitation): User {
            // Build user attributes — merge email_verified_at when coming from
            // a valid invitation (server-derived, never client-controlled).
            $userAttributes = [
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'phone'      => $data['phone'] ?? null,
                'city'       => $data['city'] ?? null,
                'gender'     => $data['gender'] ?? null,
                'role'       => $role,
                'locale'     => $data['locale'] ?? 'fr',
            ];

            if ($invitation !== null) {
                $userAttributes['email_verified_at'] = now();
            }

            $user = User::create($userAttributes);

            if ($role === UserRole::Company->value) {
                $company = Company::create([
                    'name'         => $data['company_name'],
                    'address'      => $data['address'],
                    'city'         => $data['city'] ?? '',
                    'phone'        => $data['phone'] ?? null,
                    'email'        => $data['email'],
                    'gender'       => $data['company_gender'],
                    'booking_mode' => $data['booking_mode'] ?? 'employee_based',
                ]);

                if (isset($data['latitude'], $data['longitude'])) {
                    DB::statement(
                        'UPDATE companies SET location = ST_SRID(POINT(?, ?), 4326) WHERE id = ?',
                        [(float) $data['latitude'], (float) $data['longitude'], $company->id]
                    );
                }

                // Attach the user as owner in the company_user pivot
                $company->users()->attach($user->id, [
                    'role'      => 'owner',
                    'is_active' => true,
                ]);
            }

            // --- Post-create invitation acceptance (inside same transaction) -
            if ($invitation !== null) {
                CompanyUser::create([
                    'company_id'  => $invitation->company_id,
                    'user_id'     => $user->id,
                    'role'        => $invitation->role ?? CompanyRole::Employee,
                    'specialties' => $invitation->specialties ?? [],
                    'is_active'   => true,
                ]);

                $invitation->update([
                    'status'            => InvitationStatus::Accepted,
                    'accepted_at'       => now(),
                    'resulting_user_id' => $user->id,
                ]);
            }
            // -----------------------------------------------------------------

            return $user;
        });

        // Dispatch outside the transaction so the job is only queued on commit.
        if ($invitation !== null) {
            SendInvitationDecisionPush::dispatch($invitation->fresh(), 'accepted');
        } else {
            $user->sendEmailVerificationNotification();
        }

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

    // PUT /auth/change-password
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect',
                'errors'  => ['current_password' => ['Le mot de passe actuel est incorrect']],
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès',
        ]);
    }

    // POST /auth/google
    //
    // Accepts either:
    //   - id_token      (mobile / iOS / Android — preferred, JWT verifiable locally)
    //   - access_token  (Flutter Web — google_sign_in_web only returns an OAuth2
    //                    access token via the Token Client flow; we exchange it
    //                    for user info server-side via tokeninfo + userinfo)
    public function googleLogin(Request $request): AuthResource|JsonResponse
    {
        $request->validate([
            'id_token'     => ['required_without:access_token', 'nullable', 'string'],
            'access_token' => ['required_without:id_token', 'nullable', 'string'],
            // Role hint — only honored when creating a brand-new account.
            // Existing users keep whatever role they already have so a user
            // can never be "upgraded" to company by social-login replay.
            'role'         => ['sometimes', 'nullable', 'string', 'in:user,company'],
        ]);

        $idToken     = $request->input('id_token');
        $accessToken = $request->input('access_token');

        if ($idToken) {
            // id_token flow — tokeninfo returns email + name + aud from JWT claims.
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);
            $payload = $response->failed() ? [] : $response->json();
        } else {
            // access_token flow — tokeninfo gives us aud/sub/email; userinfo gives name.
            $tokenInfo = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'access_token' => $accessToken,
            ]);
            if ($tokenInfo->failed() || empty($tokenInfo->json('email'))) {
                return response()->json([
                    'errors' => ['access_token' => ['invalid_or_expired']],
                ], 401);
            }
            $payload = $tokenInfo->json();

            // userinfo returns name/given_name/family_name/picture that tokeninfo doesn't.
            $userInfo = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');
            if ($userInfo->successful()) {
                $payload = array_merge($payload, $userInfo->json());
            }
        }

        if (empty($payload['email'])) {
            return response()->json([
                'errors' => ['token' => ['invalid_or_expired']],
            ], 401);
        }

        // Verify `aud` claim matches one of our own OAuth client IDs so an
        // attacker can't replay a token issued for a different app.
        $allowed = array_filter(array_map(
            'trim',
            explode(',', (string) config('services.google.allowed_client_ids', ''))
        ));
        if (! empty($allowed) && ! in_array($payload['aud'] ?? '', $allowed, true)) {
            return response()->json([
                'errors' => ['token' => ['aud_mismatch']],
            ], 401);
        }

        $name      = $payload['name'] ?? trim(($payload['given_name'] ?? '') . ' ' . ($payload['family_name'] ?? ''));
        $nameParts = explode(' ', $name, 2);

        $requestedRole = $request->input('role') === 'company' ? 'company' : 'user';

        $user = User::firstOrCreate(
            ['email' => $payload['email']],
            [
                'first_name'        => $nameParts[0] ?? '',
                'last_name'         => $nameParts[1] ?? '',
                'password'          => Hash::make(Str::random(32)),
                'role'              => $requestedRole,
                'email_verified_at' => now(),
            ]
        );

        [$accessToken, $refreshToken] = $this->issueTokenPair($user);

        return new AuthResource($user, $accessToken, $refreshToken);
    }

    // POST /auth/complete-company
    //
    // Used by clients who signed up via social auth as role=company — they are
    // authenticated but the Company record doesn't exist yet. This endpoint
    // creates the Company + owner pivot so their salon is fully provisioned.
    public function completeCompanySignup(Request $request): AuthResource|JsonResponse
    {
        $user = $request->user();

        if (! $user || $user->role?->value !== 'company') {
            return response()->json([
                'errors' => ['role' => ['not_a_company_account']],
            ], 403);
        }

        // Reject if the user already owns a company — idempotency safeguard.
        $alreadyHasCompany = $user->companies()
            ->wherePivot('role', 'owner')
            ->exists();
        if ($alreadyHasCompany) {
            return response()->json([
                'errors' => ['company' => ['already_setup']],
            ], 409);
        }

        $data = $request->validate([
            'company_name'   => ['required', 'string', 'max:255'],
            'address'        => ['required', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'company_gender' => ['required', 'string', 'in:men,women,both'],
            'booking_mode'   => ['nullable', 'string', 'in:employee_based,capacity_based'],
            'latitude'       => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'      => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        DB::transaction(function () use ($user, $data): void {
            $company = Company::create([
                'name'         => $data['company_name'],
                'address'      => $data['address'],
                'city'         => $data['city'] ?? '',
                'phone'        => $data['phone'] ?? null,
                'email'        => $user->email,
                'gender'       => $data['company_gender'],
                'booking_mode' => $data['booking_mode'] ?? 'employee_based',
            ]);

            if (isset($data['latitude'], $data['longitude'])) {
                DB::statement(
                    'UPDATE companies SET location = ST_SRID(POINT(?, ?), 4326) WHERE id = ?',
                    [(float) $data['latitude'], (float) $data['longitude'], $company->id]
                );
            }

            $company->users()->attach($user->id, [
                'role'      => 'owner',
                'is_active' => true,
            ]);
        });

        [$accessToken, $refreshToken] = $this->issueTokenPair($user);

        return new AuthResource($user->fresh(), $accessToken, $refreshToken);
    }

    // POST /auth/facebook
    public function facebookLogin(Request $request): AuthResource|JsonResponse
    {
        $request->validate([
            'access_token' => ['required', 'string'],
            // Role hint — only honored when creating a brand-new account.
            // Existing users keep their existing role (no social-login upgrade).
            'role'         => ['sometimes', 'nullable', 'string', 'in:user,company'],
        ]);

        $response = Http::get('https://graph.facebook.com/me', [
            'fields'       => 'id,name,email,first_name,last_name,picture',
            'access_token' => $request->input('access_token'),
        ]);

        if ($response->failed() || empty($response->json('email'))) {
            return response()->json([
                'errors' => ['access_token' => ['invalid_or_expired']],
            ], 401);
        }

        $payload = $response->json();

        $requestedRole = $request->input('role') === 'company' ? 'company' : 'user';

        $user = User::firstOrCreate(
            ['email' => $payload['email']],
            [
                'first_name'        => $payload['first_name'] ?? explode(' ', $payload['name'] ?? '', 2)[0] ?? '',
                'last_name'         => $payload['last_name']  ?? explode(' ', $payload['name'] ?? '', 2)[1] ?? '',
                'password'          => Hash::make(Str::random(32)),
                'role'              => $requestedRole,
                'email_verified_at' => now(),
            ]
        );

        [$accessToken, $refreshToken] = $this->issueTokenPair($user);

        return new AuthResource($user, $accessToken, $refreshToken);
    }

    // POST /auth/apple
    //
    // Verifies the identity_token issued by Apple by:
    //  1. Fetching Apple's JWKS (public keys) from appleid.apple.com/auth/keys
    //  2. Decoding the JWT header to pick the matching key by kid
    //  3. Verifying the signature with that RS256 public key
    //  4. Checking iss = https://appleid.apple.com and aud = configured client_id
    //  5. Looking up / creating the User by email (or sub fallback)
    //
    // Apple only sends first_name / last_name on the VERY FIRST sign-in, so the
    // client forwards whatever it received and we persist it on account creation.
    public function appleLogin(Request $request): AuthResource|JsonResponse
    {
        $request->validate([
            'identity_token' => ['required', 'string'],
            'first_name'     => ['sometimes', 'nullable', 'string', 'max:80'],
            'last_name'      => ['sometimes', 'nullable', 'string', 'max:80'],
            // Role hint — only honored when creating a brand-new account.
            'role'           => ['sometimes', 'nullable', 'string', 'in:user,company'],
        ]);

        $idToken = $request->input('identity_token');

        // --- 1. Decode header to pick the right Apple public key -------------
        $segments = explode('.', $idToken);
        if (count($segments) !== 3) {
            return response()->json([
                'errors' => ['identity_token' => ['malformed']],
            ], 401);
        }

        $header = json_decode(base64_decode(strtr($segments[0], '-_', '+/')), true);
        $kid    = $header['kid'] ?? null;
        $alg    = $header['alg'] ?? null;

        if (! $kid || $alg !== 'RS256') {
            return response()->json([
                'errors' => ['identity_token' => ['unsupported_header']],
            ], 401);
        }

        // --- 2. Fetch Apple JWKS (cached 1h to avoid hammering Apple) --------
        $jwks = cache()->remember('apple_jwks', 3600, function () {
            return Http::timeout(5)
                ->get('https://appleid.apple.com/auth/keys')
                ->json('keys') ?? [];
        });

        $key = collect($jwks)->firstWhere('kid', $kid);
        if (! $key) {
            return response()->json([
                'errors' => ['identity_token' => ['unknown_key']],
            ], 401);
        }

        // --- 3. Verify signature using firebase/php-jwt ----------------------
        try {
            $publicKey = \Firebase\JWT\JWK::parseKey($key);
            $decoded   = (array) \Firebase\JWT\JWT::decode($idToken, $publicKey);
        } catch (\Throwable $e) {
            return response()->json([
                'errors' => ['identity_token' => ['invalid_signature']],
            ], 401);
        }

        // --- 4. Validate iss and aud -----------------------------------------
        $expectedAud = config('services.apple.client_id');
        if (($decoded['iss'] ?? null) !== 'https://appleid.apple.com'
            || ($expectedAud && ($decoded['aud'] ?? null) !== $expectedAud)) {
            return response()->json([
                'errors' => ['identity_token' => ['claim_mismatch']],
            ], 401);
        }

        // --- 5. Resolve / create the user ------------------------------------
        $email = $decoded['email'] ?? null;
        $sub   = $decoded['sub']   ?? null;

        if (! $email && ! $sub) {
            return response()->json([
                'errors' => ['identity_token' => ['missing_identity']],
            ], 401);
        }

        // Apple "hide my email" relays still have a stable email per-app — use
        // it as the unique identifier, fall back to sub@apple.invalid so we
        // never end up with a user without an email column.
        $lookupEmail = $email ?? ($sub . '@apple.invalid');

        $requestedRole = $request->input('role') === 'company' ? 'company' : 'user';

        $user = User::firstOrCreate(
            ['email' => $lookupEmail],
            [
                'first_name'        => (string) $request->input('first_name', ''),
                'last_name'         => (string) $request->input('last_name', ''),
                'password'          => Hash::make(Str::random(32)),
                'role'              => $requestedRole,
                'email_verified_at' => now(),
            ]
        );

        [$accessToken, $refreshToken] = $this->issueTokenPair($user);

        return new AuthResource($user, $accessToken, $refreshToken);
    }

    // GET /auth/check-email?email=...
    public function checkEmail(Request $request): JsonResponse
    {
        $email = (string) $request->query('email', '');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['available' => null], 422);
        }

        $exists = User::where('email', $email)->exists();

        return response()->json(['available' => ! $exists]);
    }

    // POST /auth/forgot-password
    //
    // Emails a 6-character code (hashed in password_reset_tokens) in the
    // recipient's locale. Always returns 200 regardless of whether the
    // email exists, to prevent user enumeration.
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user) {
            $user->sendPasswordResetCode();
        }

        return response()->json(null, 200);
    }

    // POST /auth/reset-password
    //
    // Validates the 6-character code issued by forgotPassword, replaces the
    // password, revokes all existing sessions/refresh tokens, and consumes
    // the reset token. Mirrors the verify-email flow (OTP, no signed URL).
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $record || ! Hash::check($data['token'], $record->token)) {
            return response()->json([
                'errors' => ['token' => ['invalid_or_expired']],
            ], 422);
        }

        // Tokens are valid for 60 minutes from issuance.
        $createdAt = \Illuminate\Support\Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

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

        $user->forceFill([
            'password'              => Hash::make($data['password']),
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ])->save();

        $user->tokens()->delete();
        $user->refreshTokens()->update(['revoked_at' => now()]);

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

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

        // Dispatch role-appropriate welcome email at T+5 min so the user
        // first sees the OTP confirmation screen before the email arrives.
        if ($user->role?->value === 'company') {
            SendWelcomeOwnerEmail::dispatch($user)->delay(now()->addMinutes(5));
        } else {
            SendWelcomeClientEmail::dispatch($user)->delay(now()->addMinutes(5));
        }

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

    // DELETE /auth/account
    //
    // Anonymises PII, cancels future appointments, detaches company memberships
    // and soft-deletes the user. Owners with an active salon are blocked (422)
    // so they can transfer ownership or delete the salon first — cascading the
    // salon deletion automatically is too destructive.
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // --- Guard: owner with active salon ----------------------------------
        $isOwner = CompanyUser::where('user_id', $user->id)
            ->where('role', CompanyRole::Owner->value)
            ->where('is_active', true)
            ->exists();

        if ($isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Tu dois transférer la propriété du salon ou le supprimer avant de pouvoir supprimer ton compte.',
                'code'    => 'owner_has_active_salon',
            ], 422);
        }

        DB::transaction(function () use ($user): void {
            // 1. Cancel future appointments and notify each salon.
            $futureAppointments = Appointment::where('user_id', $user->id)
                ->whereIn('status', [
                    AppointmentStatus::Pending->value,
                    AppointmentStatus::Confirmed->value,
                ])
                ->where('date', '>=', now()->toDateString())
                ->with(['service', 'user', 'company'])
                ->get();

            foreach ($futureAppointments as $appt) {
                $appt->update([
                    'status'                 => AppointmentStatus::Cancelled,
                    'cancelled_by_client_at' => now(),
                    'cancellation_reason'    => 'account_deleted',
                ]);
                SendAppointmentCancelledByClientNotification::dispatch(
                    $appt->fresh(['service', 'user', 'company'])
                );
            }

            // 2. Detach employee pivots (hard delete from company_user pivot).
            CompanyUser::where('user_id', $user->id)
                ->where('role', CompanyRole::Employee->value)
                ->delete();

            // 3. Revoke all Sanctum tokens.
            $user->tokens()->delete();

            // 4. Revoke all refresh tokens.
            $user->refreshTokens()->update(['revoked_at' => now()]);

            // 5. Remove FCM device tokens.
            $user->devices()->delete();

            // 6. Delete avatar files from storage.
            if ($user->profile_image_url) {
                $files = Storage::disk('public')->allFiles("avatars/{$user->id}");
                foreach ($files as $file) {
                    Storage::disk('public')->delete($file);
                }
            }

            // 7. Anonymise PII before soft-delete so the row carries no personal data.
            $user->forceFill([
                'first_name'        => 'Utilisateur',
                'last_name'         => 'supprimé',
                'email'             => "deleted-{$user->id}@termini-im.com",
                'phone'             => null,
                'profile_image_url' => null,
                'password'          => Hash::make(Str::random(64)),
            ])->save();

            // 8. Soft-delete (sets deleted_at).
            $user->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function issueTokenPair(User $user): array
    {
        // Short-lived access token — the Flutter Dio interceptor auto-refreshes
        // via /auth/refresh on 401, so the user never sees a re-login as long
        // as the 90-day refresh token is still valid.
        $accessTokenModel = $user->createToken(
            'access_token',
            ['*'],
            now()->addMinutes(15),
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
