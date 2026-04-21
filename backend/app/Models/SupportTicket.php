<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'phone',
        'email',
        'message',
        'attachments',
        'source_page',
        'source_context',
        'status',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'attachments'    => 'array',
            'source_context' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
