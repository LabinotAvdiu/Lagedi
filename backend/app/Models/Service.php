<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'category_id', 'name', 'description',
    'price', 'duration', 'gender', 'is_active', 'max_concurrent',
])]
class Service extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'is_active'      => 'boolean',
            'gender'         => Gender::class,
            'max_concurrent' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function companyUsers(): BelongsToMany
    {
        return $this->belongsToMany(CompanyUser::class, 'employee_service')
            ->withPivot('duration')
            ->withTimestamps();
    }

    /**
     * Alias of companyUsers() — more expressive name for the booking flow.
     */
    public function employees(): BelongsToMany
    {
        return $this->companyUsers();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
