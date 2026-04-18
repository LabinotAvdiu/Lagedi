<?php

namespace App\Models;

use App\Enums\BookingMode;
use App\Enums\Gender;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'description', 'phone', 'phone_secondary', 'email',
    'address', 'city', 'postal_code', 'country',
    'gender', 'booking_mode', 'location', 'profile_image_url',
    'rating', 'review_count', 'price_level',
])]
class Company extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            // 'location' is a MySQL POINT (binary geometry) — do NOT cast to 'array'.
            // json_decode() on a binary POINT blob throws errors and adds overhead.
            // Use DB::raw('ST_AsGeoJSON(location)') in queries that need coordinates.
            'gender'       => Gender::class,
            'booking_mode' => BookingMode::class,
            'rating'       => 'decimal:2',
            'review_count' => 'integer',
            'price_level'  => 'integer',
        ];
    }

    public function openingHours(): HasMany
    {
        return $this->hasMany(CompanyOpeningHour::class);
    }

    public function daysOff(): HasMany
    {
        return $this->hasMany(CompanyDayOff::class);
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(CompanyGalleryImage::class)->orderBy('sort_order');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'profile_photo', 'is_active')
            ->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function serviceCategories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class)->orderBy('name');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Users who have added this company to their favorites.
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_favorites')
            ->withPivot('created_at');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(CompanyBreak::class);
    }

    public function capacityOverrides(): HasMany
    {
        return $this->hasMany(CompanyCapacityOverride::class);
    }
}
