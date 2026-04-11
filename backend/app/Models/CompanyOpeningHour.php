<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'day_of_week', 'open_time', 'close_time', 'is_closed'])]
class CompanyOpeningHour extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'is_closed' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
