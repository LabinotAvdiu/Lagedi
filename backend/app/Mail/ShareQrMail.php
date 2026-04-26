<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Termini im — sends the QR code of a salon to its owner / employee
 * with the editorial caption above and "Ajoute-moi en favori et prends
 * RDV" below the QR. The QR PNG is generated client-side and passed
 * here as raw binary.
 */
class ShareQrMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Company $company,
        public readonly string $qrPngBinary,
        public readonly ?string $caption,
        public readonly ?string $employeeName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@termini-im.com', 'Termini im'),
            replyTo: [
                new Address('support@termini-im.com', 'Support Termini im'),
            ],
            subject: __('emails.share_qr.subject', [
                'salon' => $this->company->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.share_qr',
            with: [
                'user'         => $this->user,
                'company'      => $this->company,
                'caption'      => $this->caption ?? $this->company->name,
                'employeeName' => $this->employeeName,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->qrPngBinary, 'termini-im-qr.png')
                ->withMime('image/png'),
        ];
    }
}
