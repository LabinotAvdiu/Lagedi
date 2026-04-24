<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvitationStatus;
use App\Http\Resources\PublicInvitationResource;
use App\Models\EmployeeInvitation;
use Illuminate\Http\JsonResponse;

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
}
