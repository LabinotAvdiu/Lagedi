<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientErrorsRequest;
use App\Models\ClientError;
use App\Models\CompanyUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * E28 — Collecte et consultation des erreurs Flutter.
 *
 * POST /api/errors      — public, accepte un batch d'erreurs (max 50).
 * GET  /api/errors      — auth:sanctum + owner gate, 100 dernières erreurs.
 */
class ClientErrorController extends Controller
{
    // =========================================================================
    // POST /api/errors
    // =========================================================================

    /**
     * Enregistre un batch d'erreurs Flutter.
     *
     * Pas de middleware auth — les erreurs peuvent survenir avant le login.
     * On lit silencieusement l'ID utilisateur via Sanctum si un token valide
     * est présent dans la requête.
     *
     * Retourne 204 No Content — pas de body pour éviter qu'un éventuel
     * problème de parsing de réponse génère une nouvelle erreur côté client.
     */
    public function store(StoreClientErrorsRequest $request): Response
    {
        $userId = auth('sanctum')->id();
        $rows   = $request->validated()['errors'];

        DB::transaction(function () use ($rows, $userId): void {
            foreach ($rows as $row) {
                ClientError::create([
                    'user_id'     => $userId,
                    'platform'    => $row['platform'],
                    'app_version' => $row['app_version'],
                    'error_type'  => $row['error_type'],
                    'message'     => $row['message'],
                    'stack_trace' => $row['stack_trace'] ?? null,
                    'route'       => $row['route'] ?? null,
                    'http_status' => $row['http_status'] ?? null,
                    'http_url'    => $row['http_url'] ?? null,
                    'context'     => $row['context'] ?? null,
                    'occurred_at' => $row['occurred_at'],
                ]);
            }
        });

        return response()->noContent(); // 204
    }

    // =========================================================================
    // GET /api/errors  (auth:sanctum — owner gate)
    // =========================================================================

    /**
     * Liste les 100 erreurs les plus récentes, filtrables par query params.
     *
     * Gate : l'utilisateur doit être owner d'au moins une entreprise.
     * Labinot (owner du salon seed) y accède donc nativement.
     *
     * Filtres disponibles :
     *   ?platform=android|ios|web
     *   ?error_type=DioException
     *   ?user_id=42
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Vérifie que l'utilisateur authentifié est owner d'au moins une company.
        $isOwner = CompanyUser::where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();

        if (! $isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $query = ClientError::query()
            ->orderByDesc('occurred_at')
            ->limit(100);

        if ($request->filled('platform')) {
            $query->where('platform', $request->query('platform'));
        }

        if ($request->filled('error_type')) {
            $query->where('error_type', $request->query('error_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        }

        $errors = $query->get([
            'id',
            'user_id',
            'platform',
            'app_version',
            'error_type',
            'message',
            'stack_trace',
            'route',
            'http_status',
            'http_url',
            'context',
            'occurred_at',
            'received_at',
        ]);

        return response()->json([
            'success' => true,
            'data'    => $errors,
        ]);
    }
}
