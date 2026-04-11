<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_user_id', 'day_of_week', 'start_time', 'end_time'])]
class EmployeeSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
        ];
    }

    public function companyUser(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class);
    }
}
