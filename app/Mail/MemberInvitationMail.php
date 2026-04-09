<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $inviterName,
        public readonly string $tenantName
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->tenantName} on TaskForge",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invitation',
            with: [
                'acceptUrl' => config('app.frontend_url', config('app.url')).'/invitations/accept?token='.$this->invitation->token,
                'inviterName' => $this->inviterName,
                'tenantName' => $this->tenantName,
                'role' => $this->invitation->role,
                'expiresAt' => $this->invitation->expires_at->format('d/m/Y H:i'),
            ],
        );
    }
}
