<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbox de notifications — journal des 30 derniers jours pour l'utilisateur.
 *
 * GET   /api/me/notifications-log?limit=50
 * PATCH /api/me/notifications-log/{id}/read
 * PATCH /api/me/notifications-log/read-all
 */
class NotificationsLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 50), 200);

        $logs = NotificationLog::where('user_id', $request->user()->id)
            ->where('sent_at', '>=', now()->subDays(30))
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get(['id', 'channel', 'type', 'payload', 'sent_at', 'read_at', 'clicked_at', 'ref_type', 'ref_id']);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    /**
     * Marque une notification comme lue (idempotent — skip si déjà read).
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $log = NotificationLog::where('user_id', $request->user()->id)
            ->find($id);

        if ($log === null) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        if ($log->read_at === null) {
            $log->read_at = now();
            $log->save();
        }

        return response()->json(['success' => true, 'data' => $log]);
    }

    /**
     * Marque toutes les notifications non lues comme lues.
     * Retourne le nombre de lignes affectées.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = NotificationLog::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'affected' => $count]);
    }
}
