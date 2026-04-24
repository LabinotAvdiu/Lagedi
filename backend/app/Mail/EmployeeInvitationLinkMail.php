<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EmployeeInvitation $invitation,
        public readonly Company $company,
        public readonly User $owner,
        public readonly string $plaintextToken,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.invitation.subject', ['company' => $this->company->name]));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.employee-invitation-link',
            with: [
                'companyName' => $this->company->name,
                'ownerName'   => trim(($this->owner->first_name ?? '') . ' ' . ($this->owner->last_name ?? '')) ?: 'Termini im',
                'firstName'   => $this->invitation->first_name,
                'token'       => $this->plaintextToken,
                'expiresAt'   => $this->invitation->expires_at,
                'deepLink'    => config('app.url') . '/invite/' . $this->plaintextToken,
            ],
        );
    }
}
