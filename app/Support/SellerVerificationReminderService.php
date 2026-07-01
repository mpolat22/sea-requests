<?php

namespace App\Support;

use App\Models\User;

class SellerVerificationReminderService
{
    /**
     * @return array{onboarding:int,reminder_24h:int,reminder_72h:int,auto_rejected:int}
     */
    public function dispatchDueReminders(): array
    {
        $counts = [
            'onboarding' => 0,
            'reminder_24h' => 0,
            'reminder_72h' => 0,
            'auto_rejected' => 0,
        ];

        User::query()
            ->where('role', 'seller')
            ->whereNotNull('email_verified_at')
            ->whereNull('seller_verification_submitted_at')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$counts): void {
                foreach ($users as $user) {
                    $stage = $this->dueStage($user);

                    if ($stage === null) {
                        continue;
                    }

                    match ($stage) {
                        'onboarding' => $counts['onboarding'] += $this->sendOnboardingIfNeeded($user) ? 1 : 0,
                        '24h' => $counts['reminder_24h'] += $this->send24HourReminderIfNeeded($user) ? 1 : 0,
                        '72h' => $counts['reminder_72h'] += $this->send72HourReminderIfNeeded($user) ? 1 : 0,
                        'auto_reject' => $counts['auto_rejected'] += $this->autoRejectAfterFinalReminderIfNeeded($user) ? 1 : 0,
                    };
                }
            });

        return $counts;
    }

    public function sendOnboardingIfNeeded(User $user): bool
    {
        if (! $this->canReceiveVerificationEmails($user) || $user->seller_verification_onboarding_sent_at !== null) {
            return false;
        }

        MarketplaceNotificationCenter::notifySellerVerificationOnboarding($user);

        $user->forceFill([
            'seller_verification_onboarding_sent_at' => now(),
        ])->save();

        return true;
    }

    public function send24HourReminderIfNeeded(User $user): bool
    {
        if (
            ! $this->canReceiveVerificationEmails($user)
            || $user->seller_verification_24h_reminder_sent_at !== null
            || ! $user->email_verified_at?->lte(now()->subHours(24))
        ) {
            return false;
        }

        MarketplaceNotificationCenter::notifySellerVerificationReminder($user, 24);

        $user->forceFill([
            'seller_verification_onboarding_sent_at' => $user->seller_verification_onboarding_sent_at ?: now(),
            'seller_verification_24h_reminder_sent_at' => now(),
        ])->save();

        return true;
    }

    public function send72HourReminderIfNeeded(User $user): bool
    {
        if (
            ! $this->canReceiveVerificationEmails($user)
            || $user->seller_verification_72h_reminder_sent_at !== null
            || ! $user->email_verified_at?->lte(now()->subHours(72))
        ) {
            return false;
        }

        MarketplaceNotificationCenter::notifySellerVerificationReminder($user, 72);

        $user->forceFill([
            'seller_verification_onboarding_sent_at' => $user->seller_verification_onboarding_sent_at ?: now(),
            'seller_verification_72h_reminder_sent_at' => now(),
        ])->save();

        return true;
    }

    public function autoRejectAfterFinalReminderIfNeeded(User $user): bool
    {
        if (
            ! $this->canAutoRejectAfterFinalReminder($user)
            || ! $user->seller_verification_72h_reminder_sent_at?->lte(now()->subHours(24))
        ) {
            return false;
        }

        $note = 'Your supplier verification was not completed within the required timeframe. '
            .'Your profile remains unpublished and RFQ access is not active. '
            .'You can log in and complete your verification to request a new review.';

        $user->forceFill([
            'approval_status' => 'rejected',
            'approved_at' => null,
            'seller_rejection_reason' => 'documents_incomplete',
            'seller_rejection_note' => $note,
            'seller_rejection_fields' => [],
            'seller_rejected_at' => now(),
        ])->save();

        app(SupplierServiceListingIndex::class)->clearSeller($user);

        MarketplaceNotificationCenter::notifyApprovalDecision($user, 'rejected', [
            'reason' => $user->seller_rejection_reason,
            'note' => $user->seller_rejection_note,
            'fields' => $user->seller_rejection_fields ?? [],
        ]);

        return true;
    }

    private function dueStage(User $user): ?string
    {
        if (! $this->canReceiveVerificationEmails($user)) {
            return null;
        }

        if ($this->canAutoRejectAfterFinalReminder($user)) {
            return 'auto_reject';
        }

        if (
            $user->seller_verification_72h_reminder_sent_at === null
            && $user->email_verified_at?->lte(now()->subHours(72))
        ) {
            return '72h';
        }

        if (
            $user->seller_verification_24h_reminder_sent_at === null
            && $user->email_verified_at?->lte(now()->subHours(24))
        ) {
            return '24h';
        }

        if ($user->seller_verification_onboarding_sent_at === null) {
            return 'onboarding';
        }

        return null;
    }

    private function canReceiveVerificationEmails(User $user): bool
    {
        return $user->isSeller()
            && $user->hasVerifiedEmail()
            && $user->seller_verification_submitted_at === null;
    }

    private function canAutoRejectAfterFinalReminder(User $user): bool
    {
        return $user->isSeller()
            && $user->hasVerifiedEmail()
            && $user->seller_verification_submitted_at === null
            && $user->approval_status !== 'rejected'
            && $user->seller_rejected_at === null
            && $user->seller_verification_72h_reminder_sent_at !== null;
    }
}
