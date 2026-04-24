<?php

declare(strict_types=1);

namespace App\Models;

use App\Mail\ResetPasswordMail;
use App\Mail\VerifyEmailMail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'city',
        'gender',
        'role',
        'profile_image_url',
        'failed_login_attempts',
        'locked_until',
        'locale',
        'timezone',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'failed_login_attempts',
        'locked_until',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'locked_until'           => 'datetime',
            'failed_login_attempts'  => 'integer',
            'role'                   => UserRole::class,
        ];
    }

    // -------------------------------------------------------------------------
    // Account lockout helpers
    // -------------------------------------------------------------------------

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_login_attempts');

        if ($this->fresh()->failed_login_attempts >= 10) {
            $this->update(['locked_until' => now()->addMinutes(30)]);
        }
    }

    public function clearFailedAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // One-time code generator
    // -------------------------------------------------------------------------

    /**
     * 6-character uppercase code for email verification / password reset.
     *
     * Alphabet excludes the visually ambiguous pairs 0/O and 1/I so users
     * reading the code off an email and typing it into a 6-cell OTP input
     * don't get tripped up.
     */
    private static function generateOtpCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 32 chars
        $max      = strlen($alphabet) - 1;
        $out      = '';

        for ($i = 0; $i < 6; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Email verification — custom token stored in email_verification_tokens
    // -------------------------------------------------------------------------

    public function sendEmailVerificationNotification(): void
    {
        $plainToken  = self::generateOtpCode(); // e.g. "A3B7KP" — no 0/O/1/I
        $hashedToken = Hash::make($plainToken);

        \DB::table('email_verification_tokens')->upsert(
            [
                'email'      => $this->email,
                'token'      => $hashedToken,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
            ],
            ['email'],                                // unique key
            ['token', 'expires_at', 'created_at'],    // updated on conflict
        );

        Mail::to($this->email)
            ->locale($this->locale ?? config('app.fallback_locale', 'fr'))
            ->send(new VerifyEmailMail($this, $plainToken));
    }

    /**
     * Generate a 6-character one-time code, persist it (hashed) in
     * password_reset_tokens, and email it to the user in their locale.
     *
     * Mirrors the verify-email flow so the mobile app has a single
     * "enter the code" UX instead of a clickable link.
     */
    public function sendPasswordResetCode(): string
    {
        $plainToken  = self::generateOtpCode();
        $hashedToken = Hash::make($plainToken);

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->email],
            [
                'token'      => $hashedToken,
                'created_at' => now(),
            ],
        );

        Mail::to($this->email)
            ->locale($this->locale ?? config('app.fallback_locale', 'fr'))
            ->send(new ResetPasswordMail($this, $plainToken));

        return $plainToken;
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot('role', 'profile_photo', 'is_active')
            ->withTimestamps();
    }

    /**
     * The company this user owns (role = owner in company_user pivot).
     */
    public function ownedCompany(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->wherePivot('role', 'owner')
            ->withPivot('role', 'profile_photo', 'is_active')
            ->withTimestamps();
    }

    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * The companies this user has marked as favorite.
     *
     * Ordered by created_at ASC so the oldest-added favorite is first,
     * matching the home listing promotion order.
     */
    public function favoriteCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_favorites')
            // The pivot has only created_at (no updated_at).
            // Using withPivot() instead of withTimestamps() because withTimestamps()
            // always SELECTs both created_at AND updated_at — which would throw a
            // "column not found" error since our migration intentionally omits updated_at.
            ->withPivot('created_at')
            ->orderBy('company_favorites.created_at', 'asc');
    }

    // -------------------------------------------------------------------------
    // Push notifications
    // -------------------------------------------------------------------------

    public function notificationPreference(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserNotificationPreference::class);
    }

    /**
     * Retourne les préférences existantes ou en crée avec les valeurs par défaut.
     */
    public function ensureNotificationPreference(): UserNotificationPreference
    {
        return UserNotificationPreference::firstOrCreate(
            ['user_id' => $this->id],
            ['notify_new_booking' => true, 'notify_quiet_day_reminder' => true],
        );
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // -------------------------------------------------------------------------
    // D19 — Notification preferences
    // -------------------------------------------------------------------------

    public function notificationPreferences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\NotificationPreference::class);
    }

    /**
     * Vérifie si une notification est activée pour ce canal × type.
     *
     * - Toujours true pour les types transactionnels (confirmations, annulations, OTP…)
     * - Vérifie notification_preferences pour les types configurables
     */
    public function isNotificationEnabled(string $channel, string $type): bool
    {
        return \App\Services\NotificationGate::isPreferenceEnabled($this, $channel, $type);
    }

    /**
     * Seed les préférences par défaut (enabled=true) pour tous les types × canaux.
     * Non-transactionnel — appelé depuis l'Observer UserObserver.
     */
    public function seedDefaultNotificationPreferences(): void
    {
        $channels = ['push', 'email', 'in-app'];
        $types    = \App\Enums\NotificationType::all();

        $rows = [];
        $now  = now();

        foreach ($channels as $channel) {
            foreach ($types as $type) {
                $rows[] = [
                    'user_id'    => $this->id,
                    'channel'    => $channel,
                    'type'       => $type,
                    'enabled'    => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // insertOrIgnore : si des lignes existent déjà (ex: re-seed), on ignore
        \App\Models\NotificationPreference::insertOrIgnore($rows);
    }
}
