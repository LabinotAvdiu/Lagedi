<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ServiceCategory extends Model
{
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
