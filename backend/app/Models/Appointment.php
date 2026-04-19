<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'company_user_id', 'service_id', 'company_id',
    'date', 'start_time', 'end_time', 'status', 'notes',
    'is_walk_in', 'walk_in_first_name', 'walk_in_last_name', 'walk_in_phone',
    'cancelled_by_client_at', 'cancellation_reason',
])]
class Appointment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date'                  => 'date',
            'status'                => AppointmentStatus::class,
            'is_walk_in'            => 'boolean',
            'cancelled_by_client_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function companyUser(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Retourne le datetime complet du début du RDV (date + start_time combinés).
     */
    public function getStartsAtAttribute(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->start_time);
    }
}
