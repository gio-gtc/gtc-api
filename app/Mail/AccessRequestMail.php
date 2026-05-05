<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccessRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Platform Access Request: ' . $this->data['first_name'] . ' ' . $this->data['last_name'],
            replyTo: [$this->data['email']], // Lets you hit "Reply" directly to the user!
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.access-request',
        );
    }
}