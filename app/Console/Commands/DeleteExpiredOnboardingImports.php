<?php

namespace App\Console\Commands;

use App\Models\OutreachContact;
use App\Models\User;
use App\Notifications\PreRegisteredAccountRemovedNotification;
use App\Support\UserFacingMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DeleteExpiredOnboardingImports extends Command
{
    protected $signature = 'onboarding:delete-expired-imports {--dry-run : Count records without deleting them}';

    protected $description = 'Delete bulk imported pre-registration accounts that did not complete registration within 14 days.';

    public function handle(UserFacingMail $mail): int
    {
        $counts = [
            'deleted' => 0,
            'skipped_completed' => 0,
            'notified' => 0,
            'notification_failed' => 0,
        ];

        OutreachContact::query()
            ->whereIn('audience', ['seller', 'buyer'])
            ->where('source_payload->created_from', 'bulk_company_import')
            ->whereNotNull('source_payload->bulk_import_expires_at')
            ->orderBy('id')
            ->chunkById(100, function ($contacts) use (&$counts, $mail): void {
                foreach ($contacts as $contact) {
                    $payload = $contact->source_payload ?? [];
                    $expiresAt = $this->parseDate(data_get($payload, 'bulk_import_expires_at'));

                    if (! $expiresAt || $expiresAt->isFuture()) {
                        continue;
                    }

                    $user = $this->findUser($contact);

                    if ($this->isCompleted($contact, $user)) {
                        $contact->forceFill([
                            'source_payload' => array_merge($payload, [
                                'onboarding_status' => 'completed',
                                'completed_at' => data_get($payload, 'completed_at') ?: now()->toIso8601String(),
                            ]),
                        ])->save();
                        $counts['skipped_completed']++;
                        continue;
                    }

                    if ($user) {
                        $result = $mail->attempt(fn () => $user->notify(new PreRegisteredAccountRemovedNotification()));

                        if ($result['ok']) {
                            $counts['notified']++;
                        } else {
                            $counts['notification_failed']++;
                        }

                        if (! $this->option('dry-run')) {
                            $user->delete();
                        }
                    }

                    if (! $this->option('dry-run')) {
                        $contact->delete();
                    }

                    $counts['deleted']++;
                }
            });

        $this->info(sprintf(
            'Expired onboarding imports processed. deleted=%d, skipped_completed=%d, notified=%d, notification_failed=%d',
            $counts['deleted'],
            $counts['skipped_completed'],
            $counts['notified'],
            $counts['notification_failed'],
        ));

        return self::SUCCESS;
    }

    private function findUser(OutreachContact $contact): ?User
    {
        $payload = $contact->source_payload ?? [];

        if ($userId = data_get($payload, 'user_id')) {
            $user = User::query()->find($userId);

            if ($user) {
                return $user;
            }
        }

        return User::query()->where('email', $contact->email)->first();
    }

    private function isCompleted(OutreachContact $contact, ?User $user): bool
    {
        $payload = $contact->source_payload ?? [];

        if (! $user) {
            return false;
        }

        if ($user->isSeller()) {
            return $user->seller_verification_submitted_at !== null
                || $user->approval_status === 'approved';
        }

        return filled(data_get($payload, 'account_completed_at'));
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}