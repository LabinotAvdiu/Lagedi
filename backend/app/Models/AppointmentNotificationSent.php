<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentNotificationSent extends Model
{
    protected $table = 'appointment_notifications_sent';

    public $timestamps = false;

    protected $fillable = [
        'appointment_id',
        'user_id',
        'type',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Helpers statiques
    // -------------------------------------------------------------------------

    /**
     * Vérifie si cette combinaison (type, appointment, user) a déjà été envoyée.
     */
    public static function alreadySent(int $appointmentId, int $userId, string $type): bool
    {
        return static::where('appointment_id', $appointmentId)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->exists();
    }

    /**
     * Marque la combinaison comme envoyée.
     * Utilise insertOrIgnore pour être idempotent en cas de race condition.
     */
    public static function markSent(int $appointmentId, int $userId, string $type): void
    {
        static::insertOrIgnore([
            'appointment_id' => $appointmentId,
            'user_id'        => $userId,
            'type'           => $type,
            'sent_at'        => now(),
        ]);
    }
}
