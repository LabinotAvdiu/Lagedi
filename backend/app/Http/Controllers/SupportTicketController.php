<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\SupportTicketReceived;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    private const ALLOWED_SOURCES = [
        'settings',
        'my_company',
        'company_detail',
        'login',
        'signup',
        'desktop_menu',
    ];

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    private const SUPPORT_INBOX = 'labinotavdiu.dev@gmail.com';

    // =========================================================================
    // POST /api/support-tickets
    // Public endpoint — available to guests and authenticated users.
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'              => ['required', 'string', 'min:1', 'max:100'],
            'phone'                   => ['required', 'string', 'min:4', 'max:50'],
            'email'                   => ['nullable', 'email', 'max:255'],
            'message'                 => ['required', 'string', 'min:10', 'max:2000'],
            'source_page'             => ['required', 'string', Rule::in(self::ALLOWED_SOURCES)],
            'source_context'          => ['nullable', 'array'],
            'source_context.companyId'=> ['nullable', 'string', 'max:50'],
            'source_context.locale'   => ['nullable', 'string', 'max:10'],
            'source_context.platform' => ['nullable', 'string', 'max:20'],
            'source_context.appVersion' => ['nullable', 'string', 'max:30'],
            'attachments'             => ['nullable', 'array', 'max:3'],
            'attachments.*'           => [
                'file',
                'mimes:jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'max:5120', // 5 MB
            ],
        ]);

        $userId   = auth()->check() ? (int) auth()->id() : null;
        $ticketId = (string) Str::uuid();
        $stored   = [];

        // Persist attachments first so we can reference their paths in the row.
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                // Double-check real MIME on raw bytes (blocks spoofed extensions)
                $finfo    = new \finfo(FILEINFO_MIME_TYPE);
                $realMime = $finfo->file($file->getRealPath());
                if (! in_array($realMime, self::ALLOWED_MIMES, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unsupported attachment type.',
                    ], 422);
                }

                $name     = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
                $path     = "support/{$ticketId}/{$name}";
                Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

                $stored[] = [
                    'path'          => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime'          => $realMime,
                    'size_bytes'    => $file->getSize(),
                ];
            }
        }

        $ticket = SupportTicket::create([
            'user_id'        => $userId,
            'first_name'     => trim($validated['first_name']),
            'phone'          => trim($validated['phone']),
            'email'          => $validated['email'] ?? (auth()->check() ? auth()->user()->email : null),
            'message'        => trim($validated['message']),
            'attachments'    => $stored !== [] ? $stored : null,
            'source_page'    => $validated['source_page'],
            'source_context' => $validated['source_context'] ?? null,
            'status'         => 'new',
        ]);

        // Notify support inbox. Mail failure should not block the user-visible
        // response — the ticket is already saved.
        try {
            Mail::to(self::SUPPORT_INBOX)->send(new SupportTicketReceived($ticket));
        } catch (\Throwable $e) {
            Log::error('Support ticket mail failed', [
                'ticket_id' => $ticket->id,
                'error'     => $e->getMessage(),
            ]);
        }

        return response()->json([
            'id'        => $ticket->id,
            'status'    => $ticket->status,
            'createdAt' => $ticket->created_at?->toIso8601String(),
        ], 201);
    }
}
