<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name:string,email:string,phone:string,subject:?string,message:string,agree_to_contact:mixed}  $payload
     */
    public function __construct(
        public array $payload
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.support_mail.from.address', 'support@searequests.ai');
        $fromName = (string) config('mail.support_mail.from.name', config('app.name'));

        return new Envelope(
            subject: 'Sea Requests | Contact Form Message',
            from: new Address($fromAddress, $fromName),
            replyTo: [
                new Address($this->payload['email'], $this->payload['name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-message',
            with: [
                'payload' => $this->payload,
            ],
        );
    }
}
