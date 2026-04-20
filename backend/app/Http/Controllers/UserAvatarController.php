<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class UserAvatarController extends Controller
{
    // =========================================================================
    // POST /api/me/avatar
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => [
                'required',
                'file',
                'mimes:jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:8192',
            ],
        ]);

        $file = $request->file('photo');

        // Triple MIME check: finfo on real bytes (blocks spoofed extensions)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getRealPath());
        $allowed  = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($realMime, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['photo' => ['Only JPEG, PNG, and WebP images are accepted.']],
            ], 422);
        }

        /** @var User $user */
        $user   = auth()->user();
        $userId = $user->id;
        $uuid   = Str::uuid()->toString();

        // Delete existing avatar files before writing new ones
        $this->deleteAvatarFiles($userId);

        // Process with Intervention Image v3 (GD driver) — strip EXIF implicitly
        // by re-encoding to JPEG (GD does not carry EXIF forward).
        $manager = new ImageManager(new Driver());

        // Medium — square crop 512×512, quality 88
        $medium     = $manager->read($file->getRealPath());
        $medium->cover(512, 512);
        $mediumPath = "avatars/{$userId}/medium/{$uuid}.jpg";
        Storage::disk('public')->put($mediumPath, $medium->toJpeg(88)->toString());

        // Thumb — square crop 128×128, quality 80
        $thumb     = $manager->read($file->getRealPath());
        $thumb->cover(128, 128);
        $thumbPath = "avatars/{$userId}/thumb/{$uuid}.jpg";
        Storage::disk('public')->put($thumbPath, $thumb->toJpeg(80)->toString());

        $mediumUrl = Storage::disk('public')->url($mediumPath);
        $thumbUrl  = Storage::disk('public')->url($thumbPath);

        // Persist the absolute URL of the medium variant
        $user->update(['profile_image_url' => $mediumUrl]);

        // Invalidate company detail caches for every company this user belongs to
        $this->invalidateCompanyCaches($userId);

        return response()->json([
            'profileImageUrl' => $mediumUrl,
            'thumbnailUrl'    => $thumbUrl,
        ]);
    }

    // =========================================================================
    // DELETE /api/me/avatar
    // =========================================================================

    public function destroy(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $this->deleteAvatarFiles($user->id);
        $user->update(['profile_image_url' => null]);

        $this->invalidateCompanyCaches($user->id);

        return response()->json(null, 204);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Deletes all files under avatars/{userId}/ on the public disk.
     */
    private function deleteAvatarFiles(int $userId): void
    {
        $files = Storage::disk('public')->allFiles("avatars/{$userId}");

        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
        }
    }

    /**
     * Drops the cached company detail for every company this user is a member of.
     */
    private function invalidateCompanyCaches(int $userId): void
    {
        $companyIds = CompanyUser::where('user_id', $userId)
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            Cache::forget("company:detail:{$companyId}");
        }
    }
}
