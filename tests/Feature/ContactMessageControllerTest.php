<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactMessageControllerTest extends TestCase
{
    public function test_contact_form_sends_message_through_support_mailer(): void
    {
        config([
            'mail.support_mail.recipient' => 'support@searequests.ai',
            'mail.support_mail.from.address' => 'support@searequests.ai',
            'mail.support_mail.from.name' => 'Sea Requests Support',
        ]);

        Mail::shouldReceive('mailer')
            ->once()
            ->with('support')
            ->andReturnSelf();

        Mail::shouldReceive('to')
            ->once()
            ->with('support@searequests.ai')
            ->andReturnSelf();

        Mail::shouldReceive('send')
            ->once()
            ->withArgs(function (ContactMessageMail $mail): bool {
                $envelope = $mail->envelope();

                return $mail->payload['email'] === 'mustafa@example.com'
                    && $mail->payload['name'] === 'Mustafa Polat'
                    && $envelope->from?->address === 'support@searequests.ai'
                    && $envelope->from?->name === 'Sea Requests Support'
                    && ($envelope->replyTo[0]?->address ?? null) === 'mustafa@example.com';
            });

        $response = $this->from(route('contact'))->post(route('contact.send'), [
            'name' => 'Mustafa Polat',
            'email' => 'mustafa@example.com',
            'phone' => '+905551112233',
            'subject' => 'Marketplace question',
            'message' => 'I would like to learn more about supplier onboarding.',
            'agree_to_contact' => '1',
        ]);

        $response
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', 'Your message has been sent successfully.');
    }
}
