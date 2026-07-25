<?php

namespace App\Support\Onboarding;

use App\Models\OutreachContact;
use App\Models\User;
use App\Notifications\PreRegisteredAccountCompletionNotification;
use App\Support\UserFacingMail;
use Illuminate\Support\Facades\Password;

class OnboardingCompletionMailer
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function send(OutreachContact $contact, UserFacingMail $mail): array
    {
        $payload = $contact->source_payload ?? [];
        $user = User::query()->find($payload['user_id'] ?? null);

        if (! $user) {
            return [
                'ok' => false,
                'message' => 'Create the platform account before sending the completion email.',
            ];
        }

        $token = Password::broker()->createToken($user);
        $completionUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);

        $result = $mail->attempt(fn () => $user->notify(
            new PreRegisteredAccountCompletionNotification($completionUrl, $user->role)
        ));

        if (! $result['ok']) {
            return [
                'ok' => false,
                'message' => 'Completion email could not be sent. Please check the configured mail account.',
            ];
        }

        $updates = [
            'email_verified_at' => $user->email_verified_at ?: now(),
        ];

        if ($user->isSeller()) {
            $updates['seller_verification_onboarding_sent_at'] = $user->seller_verification_onboarding_sent_at ?: now();
        }

        $user->forceFill($updates)->save();

        $contact->forceFill([
            'last_sent_at' => now(),
            'sent_count' => $contact->sent_count + 1,
            'last_result' => 'completion_email_sent',
            'source_payload' => array_merge($payload, [
                'onboarding_status' => 'email_sent',
                'completion_email_sent_at' => now()->toIso8601String(),
                'user_id' => $user->id,
            ]),
        ])->save();

        return [
            'ok' => true,
            'message' => 'Account completion email sent.',
        ];
    }
}
