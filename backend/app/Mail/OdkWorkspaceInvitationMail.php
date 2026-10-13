<?php

namespace App\Mail;

use App\Models\OdkWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OdkWorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly OdkWorkspace $workspace,
        public readonly string $inviteUrl,
        public readonly string $roleKey,
        public readonly string $expiresAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to join ' . $this->workspace->name . ' on DataKit',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.odk-workspace-invitation');
    }
}
