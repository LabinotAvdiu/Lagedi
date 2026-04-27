<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminSupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status'   => ['nullable', 'string', 'in:open,resolved'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $paginator = SupportTicket::query()
            ->when($validated['status'] ?? null, function ($q, string $status): void {
                $status === 'open'
                    ? $q->whereIn('status', ['new', 'in_progress'])
                    : $q->where('status', 'resolved');
            })
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        $data = $paginator->getCollection()->map(function (SupportTicket $ticket): array {
            $attachmentUrls = collect($ticket->attachments ?? [])
                ->pluck('path')
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->map(fn (string $path) => Storage::disk('public')->url($path))
                ->values()
                ->all();

            return [
                'id'              => $ticket->id,
                'first_name'      => $ticket->first_name,
                'phone'           => $ticket->phone,
                'email'           => $ticket->email,
                'message'         => $ticket->message,
                'source_page'     => $ticket->source_page,
                'status'          => $this->toApiStatus((string) $ticket->status),
                'attachment_urls' => $attachmentUrls,
                'created_at'      => $ticket->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function updateStatus(Request $request, int $id): Response
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:resolved'],
        ]);

        $ticket = SupportTicket::query()->findOrFail($id);

        $updates = ['status' => $validated['status']];

        if ($ticket->resolved_at === null) {
            $updates['resolved_at']     = now();
            $updates['resolved_by_id']  = Auth::id();
        }

        $ticket->update($updates);

        return response()->noContent();
    }

    private function toApiStatus(string $status): string
    {
        if (in_array($status, ['new', 'in_progress'], true)) {
            return 'open';
        }

        return $status;
    }
}
