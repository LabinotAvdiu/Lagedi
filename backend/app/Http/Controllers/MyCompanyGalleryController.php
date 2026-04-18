<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Http\Requests\MyCompany\ReorderGalleryRequest;
use App\Http\Requests\MyCompany\StoreGalleryPhotoRequest;
use App\Http\Resources\GalleryImageResource;
use App\Models\Company;
use App\Models\CompanyGalleryImage;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class MyCompanyGalleryController extends Controller
{
    // -------------------------------------------------------------------------
    // Cache invalidation
    // -------------------------------------------------------------------------

    /**
     * Drop the cached detail + public-list view after any gallery mutation so
     * new uploads / deletes / reorders are visible immediately instead of
     * lagging up to 5 minutes behind the cache TTL.
     */
    private function invalidateCompanyCaches(int $companyId): void
    {
        Cache::forget("company:detail:{$companyId}");
        // List cache is keyed per-user/version; bump the global list TTL by
        // busting the anon key family. The per-user version tokens already
        // handle auth users via their own bump path (favorites toggle).
        // Simplest safe approach: wildcard would need tagged cache — instead
        // we let the list cache expire on its 5-min TTL for now.
    }

    // -------------------------------------------------------------------------
    // Tenant isolation helper
    // -------------------------------------------------------------------------

    private function resolveOwnedCompany(): Company|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $pivot = CompanyUser::where('user_id', $user->id)
            ->where('role', CompanyRole::Owner->value)
            ->first();

        if (! $pivot) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own a company.',
            ], 403);
        }

        $company = Company::find($pivot->company_id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        return $company;
    }

    // =========================================================================
    // GET /api/my-company/gallery
    // =========================================================================

    public function index(): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $images = CompanyGalleryImage::where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return GalleryImageResource::collection($images);
    }

    // =========================================================================
    // POST /api/my-company/gallery
    // =========================================================================

    public function store(StoreGalleryPhotoRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $file      = $request->file('photo');
        $uuid      = Str::uuid()->toString();
        $directory = "gallery/{$company->id}";

        // Validate actual MIME via magic bytes (not just extension)
        $mime = $file->getMimeType();
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];

        if (! in_array($mime, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['photo' => ['Only JPEG, PNG, and WebP images are accepted.']],
            ], 422);
        }

        // Store original
        $originalPath = $file->storeAs($directory . '/original', $uuid . '.jpg', 'public');

        // Process with Intervention Image v3 (GD driver)
        $manager = new ImageManager(new Driver());
        $image   = $manager->read($file->getRealPath());

        // Medium: 1200px longest side, 85% JPEG quality
        $medium = clone $image;
        $medium->scaleDown(width: 1200, height: 1200);
        $mediumPath = $directory . '/medium/' . $uuid . '.jpg';
        Storage::disk('public')->put($mediumPath, $medium->toJpeg(85)->toString());

        // Thumbnail: 400px longest side, 80% JPEG quality
        $thumb = clone $image;
        $thumb->scaleDown(width: 400, height: 400);
        $thumbPath = $directory . '/thumb/' . $uuid . '.jpg';
        Storage::disk('public')->put($thumbPath, $thumb->toJpeg(80)->toString());

        // Next sort_order position
        $maxOrder = CompanyGalleryImage::where('company_id', $company->id)->max('sort_order') ?? -1;

        $galleryImage = CompanyGalleryImage::create([
            'company_id'     => $company->id,
            'image_path'     => $originalPath,
            'thumbnail_path' => $thumbPath,
            'medium_path'    => $mediumPath,
            'sort_order'     => $maxOrder + 1,
        ]);

        $this->invalidateCompanyCaches($company->id);

        return (new GalleryImageResource($galleryImage))
            ->response()
            ->setStatusCode(201);
    }

    // =========================================================================
    // DELETE /api/my-company/gallery/{id}
    // =========================================================================

    public function destroy(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $image = CompanyGalleryImage::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $image) {
            return response()->json([
                'success' => false,
                'message' => 'Gallery image not found.',
            ], 404);
        }

        // Delete all stored files
        foreach (['image_path', 'thumbnail_path', 'medium_path'] as $column) {
            if ($image->$column) {
                Storage::disk('public')->delete($image->$column);
            }
        }

        $image->delete();

        $this->invalidateCompanyCaches($company->id);

        return response()->json([
            'success' => true,
            'message' => 'Gallery image deleted.',
        ]);
    }

    // =========================================================================
    // POST /api/my-company/gallery/reorder
    // =========================================================================

    public function reorder(ReorderGalleryRequest $request): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $ids = $request->validated('ids');

        // Verify all IDs belong to this company
        $ownedCount = CompanyGalleryImage::where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->count();

        if ($ownedCount !== count($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'One or more image IDs do not belong to your gallery.',
                'errors'  => ['ids' => ['Invalid image IDs provided.']],
            ], 422);
        }

        // Also ensure no IDs are missing (all gallery images must be included)
        $totalImages = CompanyGalleryImage::where('company_id', $company->id)->count();

        if (count($ids) !== $totalImages) {
            return response()->json([
                'success' => false,
                'message' => 'The ids array must include all gallery images.',
                'errors'  => ['ids' => ['All gallery image IDs must be provided for reorder.']],
            ], 422);
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                CompanyGalleryImage::where('id', $id)
                    ->update(['sort_order' => $position]);
            }
        });

        $this->invalidateCompanyCaches($company->id);

        return response()->json(null, 204);
    }
}
