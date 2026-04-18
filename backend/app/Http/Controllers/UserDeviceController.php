<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserDeviceController extends Controller
{
    /**
     * POST /api/me/devices
     *
     * Enregistre ou met à jour un device token FCM.
     * Idempotent : updateOrCreate sur le token unique.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'token'    => ['required', 'string', 'max:500'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
        ]);

        $request->user()->devices()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id'      => $request->user()->id,
                'platform'     => $validated['platform'],
                'last_seen_at' => now(),
            ],
        );

        return response()->noContent();
    }

    /**
     * DELETE /api/me/devices
     *
     * Supprime un device token. Idempotent (no-op si absent).
     */
    public function destroy(Request $request): Response
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:500'],
        ]);

        $request->user()
            ->devices()
            ->where('token', $validated['token'])
            ->delete();

        return response()->noContent();
    }
}
