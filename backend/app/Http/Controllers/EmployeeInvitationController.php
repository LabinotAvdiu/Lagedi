<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvitationStatus;
use App\Http\Resources\MyInvitationResource;
use App\Http\Resources\PublicInvitationResource;
use App\Jobs\SendInvitationDecisionPush;
use App\Models\CompanyUser;
use App\Models\EmployeeInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EmployeeInvitationController extends Controller
{
    public function showByToken(string $token): JsonResponse
    {
        if (! ctype_xdigit($token) || strlen($token) !== 64) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $invitation = EmployeeInvitation::with(['company', 'invitedBy'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($invitation->status !== InvitationStatus::Pending) {
            return response()->json(['message' => 'Invitation no longer valid.'], 410);
        }

        if ($invitation->expires_at->isPast()) {
            return response()->json(['message' => 'Invitation expired.'], 410);
        }

        return response()->json([
            'data' => (new PublicInvitationResource($invitation))->toArray(request()),
        ]);
    }

    public function mine(): JsonResponse
    {
        $email = strtolower((string) auth()->user()->email);

        $invitations = EmployeeInvitation::with(['company', 'invitedBy'])
            ->where('email', $email)
            ->where('status', InvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => MyInvitationResource::collection($invitations)->resolve(),
        ]);
    }

    public function accept(int $id): JsonResponse
    {
        $email = strtolower((string) auth()->user()->email);
        $userId = auth()->id();

        $invitation = EmployeeInvitation::where('id', $id)
            ->where('email', $email)
            ->first();

        if (! $invitation) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Idempotent for already-accepted by this user.
        if ($invitation->status === InvitationStatus::Accepted
            && $invitation->resulting_user_id === $userId) {
            return response()->json(['success' => true]);
        }

        if ($invitation->status !== InvitationStatus::Pending
            || $invitation->expires_at->isPast()) {
            return response()->json(['message' => 'Invitation no longer valid.'], 410);
        }

        DB::transaction(function () use ($invitation, $userId) {
            CompanyUser::firstOrCreate(
                [
                    'company_id' => $invitation->company_id,
                    'user_id' => $userId,
                ],
                [
                    'role' => $invitation->role,
                    'specialties' => $invitation->specialties ?? [],
                    'is_active' => true,
                ],
            );
            $invitation->update([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
                'resulting_user_id' => $userId,
            ]);
        });

        SendInvitationDecisionPush::dispatch($invitation->fresh(), 'accepted');

        return response()->json(['success' => true]);
    }

    public function refuse(int $id): JsonResponse
    {
        $email = strtolower((string) auth()->user()->email);

        $invitation = EmployeeInvitation::where('id', $id)
            ->where('email', $email)
            ->first();

        if (! $invitation) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($invitation->status !== InvitationStatus::Pending) {
            return response()->json(['message' => 'Invitation no longer valid.'], 410);
        }

        $invitation->update([
            'status' => InvitationStatus::Refused,
            'refused_at' => now(),
        ]);

        SendInvitationDecisionPush::dispatch($invitation->fresh(), 'refused');

        return response()->json(['success' => true]);
    }
}
