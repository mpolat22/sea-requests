<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OutreachSenderTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
        public ?string $replyToEmail = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            from: $this->fromEmail ? new Address($this->fromEmail, $this->fromName) : null,
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.outreach.sender-test',
            with: [
                'bodyText' => $this->bodyText,
            ],
        );
    }
}
