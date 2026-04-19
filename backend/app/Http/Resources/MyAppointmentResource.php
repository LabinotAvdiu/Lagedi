<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AppointmentStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appointment shape for the "My Bookings" list in the Flutter app.
 *
 * Requires eager-loaded relations: company, service, companyUser.user
 * Optionally eager-loaded: review
 */
class MyAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Combine separate date + start_time columns into a single ISO datetime string
        $dateTime = $this->date->format('Y-m-d') . 'T' . $this->start_time;

        // companyUser → user → first_name + last_name
        $employeeName = null;
        if ($this->companyUser && $this->companyUser->user) {
            $user = $this->companyUser->user;
            $employeeName = trim($user->first_name . ' ' . $user->last_name);
        }

        $status = $this->status instanceof AppointmentStatus
            ? $this->status
            : AppointmentStatus::from((string) $this->status);

        // Feature 1 — canCancel + minutesDelta
        $minCancelHours = (int) ($this->company?->min_cancel_hours ?? 2);
        $startsAt       = Carbon::parse($this->date->format('Y-m-d') . ' ' . $this->start_time);
        $cancellableUntil = $startsAt->copy()->subHours($minCancelHours);

        $canCancel = in_array($status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)
            && ($minCancelHours === 0 || now()->lessThan($cancellableUntil));

        // Feature 2 — minutesUntilStart (null si passé)
        $minutesUntilStart = $startsAt->isFuture()
            ? (int) now()->diffInMinutes($startsAt)
            : null;

        // Feature 3 — canReview
        // Eligible si : (completed OU confirmed+passé depuis 1h) ET fenêtre 30j ET pas déjà reviewé
        $isCompleted      = $status === AppointmentStatus::Completed;
        $isConfirmedPast  = $status === AppointmentStatus::Confirmed && $startsAt->lessThan(now()->subHour());
        $withinWindow     = $startsAt->greaterThan(now()->subDays(30));
        $alreadyReviewed  = $this->relationLoaded('review') ? $this->review !== null : false;

        $canReview = ($isCompleted || $isConfirmedPast) && $withinWindow && ! $alreadyReviewed;

        return [
            'id'              => (string) $this->id,
            'companyId'       => (string) $this->company_id,
            'companyName'     => $this->company?->name,
            'companyAddress'  => $this->company?->address,
            'companyPhotoUrl' => $this->company?->profile_image_url,
            'serviceName'     => $this->service?->name,
            'employeeName'    => $employeeName,
            'dateTime'        => $dateTime,
            'durationMinutes' => $this->service ? (int) $this->service->duration : null,
            'price'           => $this->service ? (float) $this->service->price : null,
            'status'          => $status->value,

            // Feature 1 — annulation client
            'canCancel'          => $canCancel,
            'minCancelHours'     => $minCancelHours,
            'cancelsBeforeAt'    => $cancellableUntil->toIso8601String(),
            'cancellationReason' => $this->cancellation_reason,

            // Feature 2 — rappel approche
            'minutesUntilStart' => $minutesUntilStart,

            // Feature 3 — avis
            'canReview' => $canReview,
            'review'    => $this->when(
                $this->relationLoaded('review'),
                fn () => $this->review ? new ReviewResource($this->review) : null,
            ),
        ];
    }
}
