<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyPendingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;

    public function __construct(string $verificationUrl)
    {
        $this->verificationUrl = $verificationUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your New Email Address',
        );
    }

    public function content(): Content
    {
        // Using a raw markdown template so we don't have to build complex HTML right now
        return new Content(
            markdown: 'emails.verify-pending-email',
            with: [
                'url' => $this->verificationUrl,
            ]
        );
    }
}