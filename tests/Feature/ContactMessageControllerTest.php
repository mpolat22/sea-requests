<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Support\UserFacingMail;
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

    public function test_contact_form_shows_friendly_error_when_support_mail_fails(): void
    {
        $this->app->instance(UserFacingMail::class, new class extends UserFacingMail
        {
            public function attempt(callable $callback, mixed $fallbackResult = null): array
            {
                return [
                    'ok' => false,
                    'result' => $fallbackResult,
                ];
            }
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
            ->assertSessionHas('error', 'We could not send your message right now. Please try again shortly.');
    }
}
