<?php

declare(strict_types=1);

namespace App\Http\Requests\MyCompany;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreGalleryPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via resolveOwnedCompany()
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image()
                    ->max(8 * 1024) // 8 MB in kilobytes
                    ->types(['jpeg', 'jpg', 'png', 'webp']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'A photo file is required.',
            'photo.image'    => 'The file must be a valid image.',
            'photo.max'      => 'The photo may not be larger than 8 MB.',
            'photo.mimes'    => 'Only JPEG, PNG, and WebP images are accepted.',
        ];
    }
}
