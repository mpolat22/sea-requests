<?php

namespace App\Console\Commands;

use App\Support\SellerVerificationReminderService;
use Illuminate\Console\Command;

class SendSellerVerificationReminders extends Command
{
    protected $signature = 'seller-verification:send-reminders';

    protected $description = 'Send supplier verification onboarding and reminder emails after email verification.';

    public function handle(SellerVerificationReminderService $service): int
    {
        $counts = $service->dispatchDueReminders();

        $this->info(sprintf(
            'Seller verification reminders sent. onboarding=%d, reminder_24h=%d, reminder_72h=%d',
            $counts['onboarding'],
            $counts['reminder_24h'],
            $counts['reminder_72h'],
        ));

        return self::SUCCESS;
    }
}
