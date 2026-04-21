<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportTicketReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportTicket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Termini im] Nouvelle demande de support #'.$this->ticket->id,
            replyTo: $this->ticket->email !== null && $this->ticket->email !== ''
                ? [$this->ticket->email]
                : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support_ticket_received',
            with: [
                'ticket' => $this->ticket,
            ],
        );
    }

    /**
     * Build attachments from the stored files.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $files = $this->ticket->attachments ?? [];
        $out   = [];

        foreach ($files as $file) {
            $path = $file['path'] ?? null;
            if ($path === null) {
                continue;
            }

            $absolute = storage_path('app/public/'.$path);
            if (! is_file($absolute)) {
                continue;
            }

            $out[] = \Illuminate\Mail\Mailables\Attachment::fromPath($absolute)
                ->as($file['original_name'] ?? basename($path))
                ->withMime($file['mime'] ?? 'application/octet-stream');
        }

        return $out;
    }
}
