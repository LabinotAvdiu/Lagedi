<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D20 — Journal de toutes les notifications envoyées (push + email + in-app).
 *
 * @property int         $id
 * @property int         $user_id
 * @property string      $channel  push|email|in-app
 * @property string      $type
 * @property array|null  $payload
 * @property \Carbon\Carbon $sent_at
 * @property \Carbon\Carbon|null $read_at
 * @property \Carbon\Carbon|null $clicked_at
 * @property string|null $ref_type  appointment|review|walk_in…
 * @property int|null    $ref_id
 */
class NotificationLog extends Model
{
    protected $table = 'notifications_log';

    // Pas de updated_at — les lignes sont immuables après insertion.
    // On désactive les deux timestamps automatiques et on utilise sent_at
    // (défini dans la migration avec useCurrent()) à la place de created_at.
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'channel',
        'type',
        'payload',
        'sent_at',
        'read_at',
        'clicked_at',
        'ref_type',
        'ref_id',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'sent_at'    => 'datetime',
            'read_at'    => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
