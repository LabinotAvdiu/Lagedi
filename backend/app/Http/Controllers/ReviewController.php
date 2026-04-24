<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Http\Requests\Booking\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Jobs\SendNewReviewNotification;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * POST /api/appointments/{id}/review
     *
     * Client soumet un avis pour un RDV passé.
     * Conditions d'éligibilité :
     *   - Le RDV appartient à l'utilisateur
     *   - Le RDV est completed, OU confirmed avec starts_at < now() - 1h
     *   - Pas encore reviewé
     *   - Fenêtre de 30 jours depuis starts_at
     */
    public function store(StoreReviewRequest $request, int $id): ReviewResource|JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $appointment = Appointment::where('id', $id)
            ->with(['company', 'service', 'companyUser.user', 'review'])
            ->first();

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found.',
            ], 404);
        }

        if ((int) $appointment->user_id !== (int) $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $currentStatus = $appointment->status instanceof AppointmentStatus
            ? $appointment->status
            : AppointmentStatus::from((string) $appointment->status);

        $startsAt = $appointment->starts_at;

        // Vérification éligibilité : completed OU confirmed+passé depuis 1h
        $isCompleted = $currentStatus === AppointmentStatus::Completed;
        $isConfirmedPast = $currentStatus === AppointmentStatus::Confirmed
            && $startsAt->lessThan(now()->subHour());

        if (! $isCompleted && ! $isConfirmedPast) {
            return response()->json([
                'success' => false,
                'message' => 'You can only review a past appointment.',
                'errors'  => ['appointment' => ['not-reviewable-status']],
            ], 422);
        }

        // Fenêtre de 30 jours
        if ($startsAt->lessThan(now()->subDays(30))) {
            return response()->json([
                'success' => false,
                'message' => 'The review window has expired (30 days).',
                'errors'  => ['appointment' => ['review-window-expired']],
            ], 422);
        }

        // Déjà reviewé
        if ($appointment->review !== null) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this appointment.',
                'errors'  => ['appointment' => ['already-reviewed']],
            ], 422);
        }

        $review = DB::transaction(function () use ($request, $appointment, $authUser): Review {
            $review = Review::create([
                'appointment_id' => $appointment->id,
                'user_id'        => $authUser->id,
                'company_id'     => $appointment->company_id,
                'rating'         => $request->validated('rating'),
                'comment'        => $request->validated('comment'),
                'status'         => 'visible',
            ]);

            // Recalcul rating + review_count depuis les reviews visibles
            $this->recalculateCompanyRating($appointment->company_id);

            return $review;
        });

        Cache::forget("company:detail:{$appointment->company_id}");

        $review->load('user');

        // C8 — Notifie l'owner du salon qu'un nouvel avis a été publié.
        SendNewReviewNotification::dispatch($review);

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/appointments/{id}/review
     */
    public function show(int $id): ReviewResource|JsonResponse
    {
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        $appointment = Appointment::where('id', $id)
            ->where('user_id', $authUser->id)
            ->with('review.user')
            ->first();

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found.',
            ], 404);
        }

        if (! $appointment->review) {
            return response()->json([
                'success' => false,
                'message' => 'No review found for this appointment.',
            ], 404);
        }

        return new ReviewResource($appointment->review);
    }

    /**
     * GET /api/companies/{id}/reviews?page=1&per_page=10
     *
     * Public — retourne uniquement les reviews visible.
     */
    public function indexByCompany(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $company = Company::find($id);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $perPage = min((int) $request->query('per_page', 10), 50);

        $reviews = Review::where('company_id', $id)
            ->where('status', 'visible')
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ReviewResource::collection($reviews);
    }

    // -------------------------------------------------------------------------
    // Helper interne — recalcul atomique rating/review_count
    // -------------------------------------------------------------------------

    public static function recalculateCompanyRating(int $companyId): void
    {
        $agg = Review::where('company_id', $companyId)
            ->where('status', 'visible')
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
            ->first();

        Company::where('id', $companyId)->update([
            'review_count' => (int) $agg->total,
            'rating'       => $agg->total > 0 ? round((float) $agg->avg_rating, 2) : 0.00,
        ]);
    }
}
