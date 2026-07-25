<?php

namespace App\Jobs;

use App\Models\OutreachContact;
use App\Support\Onboarding\OnboardingCompletionMailer;
use App\Support\UserFacingMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOnboardingCompletionEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(public int $contactId)
    {
    }

    public function handle(OnboardingCompletionMailer $completionMailer, UserFacingMail $mail): void
    {
        $contact = OutreachContact::query()->find($this->contactId);

        if (! $contact) {
            return;
        }

        if (data_get($contact->source_payload, 'onboarding_status') === 'email_sent') {
            return;
        }

        $result = $completionMailer->send($contact, $mail);

        if ($result['ok']) {
            return;
        }

        $payload = $contact->source_payload ?? [];

        $contact->forceFill([
            'last_result' => 'completion_email_failed',
            'source_payload' => array_merge($payload, [
                'onboarding_status' => 'account_created',
                'completion_email_failed_at' => now()->toIso8601String(),
                'completion_email_error' => $result['message'],
            ]),
        ])->save();

        $this->fail($result['message']);
    }
}
