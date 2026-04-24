<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E28 — Erreur remontée depuis l'app Flutter.
 *
 * @property int|null              $user_id
 * @property string                $platform
 * @property string                $app_version
 * @property string                $error_type
 * @property string                $message
 * @property string|null           $stack_trace
 * @property string|null           $route
 * @property int|null              $http_status
 * @property string|null           $http_url
 * @property array|null            $context
 * @property \Carbon\Carbon        $occurred_at
 * @property \Carbon\Carbon        $received_at
 */
class ClientError extends Model
{
    /**
     * Pas de timestamps() Eloquent — on gère received_at via DB default,
     * et occurred_at est rempli par le client.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'platform',
        'app_version',
        'error_type',
        'message',
        'stack_trace',
        'route',
        'http_status',
        'http_url',
        'context',
        'occurred_at',
        // received_at intentionnellement absent — défaut DB CURRENT_TIMESTAMP.
    ];

    protected function casts(): array
    {
        return [
            'context'     => 'array',
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
