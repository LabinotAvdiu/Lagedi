<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeInvitation extends Model
{
    protected $fillable = [
        'company_id',
        'invited_by_user_id',
        'email',
        'first_name',
        'last_name',
        'specialties',
        'role',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'refused_at',
        'resulting_user_id',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'status'      => InvitationStatus::class,
            'expires_at'  => 'datetime',
            'accepted_at' => 'datetime',
            'refused_at'  => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function resultingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulting_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending
            && $this->expires_at->isFuture();
    }
}
