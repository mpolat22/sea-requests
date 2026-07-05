<?php

namespace App\Jobs;

use App\Support\SellerVerificationAiAutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSellerVerificationAiReviewJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public string $submittedAtIso,
    ) {
    }

    public function handle(SellerVerificationAiAutomationService $automationService): void
    {
        $automationService->processIfStillPending($this->userId, $this->submittedAtIso);
    }
}
