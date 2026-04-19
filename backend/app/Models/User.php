<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

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
    // Email verification — custom token stored in email_verification_tokens
    // -------------------------------------------------------------------------

    public function sendEmailVerificationNotification(): void
    {
        $plainToken = strtoupper(Str::random(6)); // e.g. "A3B7KP"
        $hashedToken = Hash::make($plainToken);

        \DB::table('email_verification_tokens')->upsert(
            [
                'email'      => $this->email,
                'token'      => $hashedToken,
                'expires_at' => now()->addHours(24),
                'created_at' => now(),
            ],
            ['email'],            // unique key
            ['token', 'expires_at', 'created_at'] // columns to update on conflict
        );

        $this->notify((new VerifyEmailNotification($plainToken))->locale($this->locale ?? 'fr'));
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
}
