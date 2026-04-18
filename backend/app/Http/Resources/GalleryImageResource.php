<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class GalleryImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => (string) $this->id,
            'url'          => Storage::disk('public')->url($this->medium_path ?? $this->image_path),
            'thumbnailUrl' => Storage::disk('public')->url($this->thumbnail_path ?? $this->image_path),
            'position'     => (int) $this->sort_order,
        ];
    }
}
