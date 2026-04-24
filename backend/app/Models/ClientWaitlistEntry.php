<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientWaitlistEntry extends Model
{
    protected $table = 'client_waitlist';

    protected $fillable = [
        'name', 'email', 'phone', 'city', 'source',
        'cgu_accepted_at',
        'locale', 'ip_country', 'is_diaspora',
        'utm_source', 'utm_medium', 'utm_campaign', 'referrer_url',
        'unsubscribe_token', 'status',
    ];

    protected function casts(): array
    {
        return [
            'cgu_accepted_at' => 'datetime',
            'is_diaspora'     => 'boolean',
        ];
    }
}
