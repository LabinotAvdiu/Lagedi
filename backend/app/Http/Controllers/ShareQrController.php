<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ShareQrMail;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the salon QR code by email to the authenticated owner / employee.
 *
 * The QR PNG is generated client-side (Flutter via qr_flutter) and sent
 * as base64 in the request body — the backend's only job is to attach it
 * to a Mailable and send. This avoids adding a QR-generation dependency
 * to the PHP composer (no GD/Imagick fragility, no version constraint
 * mismatch with the existing image pipeline).
 */
class ShareQrController extends Controller
{
    /**
     * POST /api/share/qr-email
     *
     * Body:
     *   - company_id     : int  (required, must belong to the user as owner or employee)
     *   - qr_png_base64  : string (required, base64-encoded PNG without data URI prefix, max ~200 KB decoded)
     *   - caption        : string (optional, ≤ 80 chars, displayed above the QR in the email)
     *   - employee_id    : int (optional, included in the body text "Avec [Nom]" when relevant)
     *
     * Returns 202 Accepted on success — mail is queued, not sent synchronously.
     */
    public function email(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'company_id'    => 'required|integer|exists:companies,id',
            'qr_png_base64' => 'required|string|max:300000', // base64 ≈ 4/3 of binary, so ~225 KB binary cap
            'caption'       => 'nullable|string|max:80',
            'employee_id'   => 'nullable|integer|exists:users,id',
        ]);

        $company = Company::find($validated['company_id']);

        // Authorisation: user must belong to the company (owner or any role).
        $belongs = $user->companies()->where('companies.id', $company->id)->exists();
        if (! $belongs) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Decode base64 and sanity-check it's actually a PNG (magic bytes).
        $binary = base64_decode($validated['qr_png_base64'], true);
        if ($binary === false || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return response()->json(['message' => 'Invalid QR PNG'], 422);
        }

        $employeeName = null;
        if (! empty($validated['employee_id'])) {
            $employee = $company->employees()->where('users.id', $validated['employee_id'])->first();
            $employeeName = $employee?->name;
        }

        Mail::to($user->email)
            ->locale($user->locale ?? 'fr')
            ->queue(new ShareQrMail(
                user: $user,
                company: $company,
                qrPngBinary: $binary,
                caption: $validated['caption'] ?? null,
                employeeName: $employeeName,
            ));

        return response()->json(['message' => 'queued'], 202);
    }
}
