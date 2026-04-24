<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D19 — Préférence de notification par (user, channel, type).
 *
 * Les types transactionnels n'ont PAS de ligne ici : ils sont toujours envoyés.
 * Seuls les types configurables (NotificationType::all()) sont stockés.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $channel  push|email|in-app
 * @property string $type
 * @property bool   $enabled
 */
class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'channel',
        'type',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
