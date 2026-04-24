<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Termini Im — employee invitation email.
 *
 * Sent immediately when an owner invites an existing user to join their
 * salon via POST /api/my-company/employees/invite. The invitation link
 * directs the employee to the login page pre-filled with the salon context.
 *
 * NOTE: The current invitation flow adds the employee immediately (no pending
 * state). The link therefore serves as a deep-link to open the app on the
 * correct salon rather than a "magic accept" link. A future token-based
 * invitation flow (D-sprint) can replace the URL without changing this class.
 */
class WelcomeEmployeeInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** The employee being invited. */
    public readonly User $employee;

    /** The owner who is inviting. */
    public readonly User $owner;

    /** The salon the employee is being invited to. */
    public readonly Company $company;

    public function __construct(User $employee, User $owner, Company $company)
    {
        $this->employee = $employee;
        $this->owner    = $owner;
        $this->company  = $company;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@termini-im.com', $this->company->name),
            replyTo: [
                new Address('support@termini-im.com', 'Support Termini im'),
            ],
            subject: __('emails.employee_invitation.subject', [
                'owner' => $this->owner->first_name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome_employee_invitation',
            with: [
                'employee' => $this->employee,
                'owner'    => $this->owner,
                'company'  => $this->company,
                // Deep-link to the app; falls back to web if app not installed.
                // TODO (D-sprint): replace with signed invitation token URL once
                // the pending-invitation flow is implemented.
                'inviteUrl' => 'https://app.termini-im.com/login?invite=' . $this->company->id,
            ],
        );
    }
}
