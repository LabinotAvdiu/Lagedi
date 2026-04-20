<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Http\Resources\ReviewResource;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class MyCompanyReviewController extends Controller
{
    /**
     * Resolve la company de l'owner/employé connecté.
     * Accepte owner ET employee.
     */
    private function resolveCompany(): Company|JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $pivot = CompanyUser::where('user_id', $user->id)
            ->whereIn('role', [CompanyRole::Owner->value, CompanyRole::Employee->value])
            ->first();

        if (! $pivot) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a member of any company.',
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

    /**
     * Vérifie que l'utilisateur connecté est OWNER de la company (hide/unhide).
     */
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

    /**
     * GET /api/my-company/reviews
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $company = $this->resolveCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $perPage = min((int) $request->query('per_page', 15), 50);

        $reviews = Review::where('company_id', $company->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ReviewResource::collection($reviews);
    }

    /**
     * PUT /api/my-company/reviews/{id}/hide
     *
     * Masque une review visible. Recalcule le rating.
     */
    public function hide(Request $request, int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $review = Review::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        if ($review->status !== 'visible') {
            return response()->json([
                'success' => false,
                'message' => 'Review is already hidden.',
            ], 422);
        }

        $review->update([
            'status'          => 'hidden_by_owner',
            'hidden_at'       => now(),
            'hidden_by'       => auth()->id(),
            'moderation_note' => $request->input('reason'),
        ]);

        ReviewController::recalculateCompanyRating($company->id);
        Cache::forget("company:detail:{$company->id}");

        $review->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Review hidden.',
            'data'    => new ReviewResource($review->fresh('user')),
        ]);
    }

    /**
     * PUT /api/my-company/reviews/{id}/unhide
     *
     * Rend une review à nouveau visible. Recalcule le rating.
     */
    public function unhide(int $id): JsonResponse
    {
        $company = $this->resolveOwnedCompany();

        if ($company instanceof JsonResponse) {
            return $company;
        }

        $review = Review::where('id', $id)
            ->where('company_id', $company->id)
            ->first();

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found.',
            ], 404);
        }

        if ($review->status !== 'hidden_by_owner') {
            return response()->json([
                'success' => false,
                'message' => 'Only owner-hidden reviews can be unhidden.',
            ], 422);
        }

        $review->update([
            'status'          => 'visible',
            'hidden_at'       => null,
            'hidden_by'       => null,
            'moderation_note' => null,
        ]);

        ReviewController::recalculateCompanyRating($company->id);
        Cache::forget("company:detail:{$company->id}");

        return response()->json([
            'success' => true,
            'message' => 'Review restored.',
            'data'    => new ReviewResource($review->fresh('user')),
        ]);
    }
}
