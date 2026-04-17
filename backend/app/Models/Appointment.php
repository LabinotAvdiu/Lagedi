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
])]
class Appointment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date'       => 'date',
            'status'     => AppointmentStatus::class,
            'is_walk_in' => 'boolean',
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
}
