<?php

namespace App\Jobs;

use App\Mail\OutreachPlainTextMail;
use App\Models\OutreachContact;
use App\Models\OutreachSendLog;
use App\Models\OutreachSenderAccount;
use App\Models\OutreachTemplate;
use App\Support\Outreach\OutreachDynamicMailer;
use App\Support\Outreach\OutreachSenderAccountManager;
use App\Support\Outreach\OutreachScheduler;
use App\Support\Outreach\OutreachTemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOutreachEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $sendLogId
    ) {
        $this->onQueue('outreach');
    }

    public function handle(
        OutreachTemplateRenderer $renderer,
        OutreachScheduler $scheduler,
        OutreachSenderAccountManager $senderAccounts,
        OutreachDynamicMailer $mailer
    ): void
    {
        $log = OutreachSendLog::query()
            ->with(['contact', 'segment', 'template', 'senderAccount'])
            ->find($this->sendLogId);

        if (! $log || ! $log->contact || ! $log->segment) {
            return;
        }

        $contact = $log->contact;

        if ($contact->status !== OutreachContact::STATUS_ACTIVE) {
            $log->forceFill([
                'status' => OutreachSendLog::STATUS_SKIPPED,
                'attempted_at' => now(),
                'error_message' => 'Contact is no longer active.',
            ])->save();

            return;
        }

        if ($scheduler->refreshRegisteredState($contact)) {
            $log->forceFill([
                'status' => OutreachSendLog::STATUS_SKIPPED,
                'attempted_at' => now(),
                'error_message' => 'Contact already has an approved supplier account.',
            ])->save();

            return;
        }

        $template = $log->template;
        $senderAccount = $log->senderAccount;

        if (! $template instanceof OutreachTemplate || ! $template->is_active) {
            $log->forceFill([
                'status' => OutreachSendLog::STATUS_FAILED,
                'attempted_at' => now(),
                'error_message' => 'Assigned outreach template is missing or inactive.',
            ])->save();

            return;
        }

        if (! $senderAccount && $log->sender_account_id) {
            $log->forceFill([
                'status' => OutreachSendLog::STATUS_FAILED,
                'attempted_at' => now(),
                'error_message' => 'Assigned sender account is missing.',
            ])->save();

            return;
        }

        if (! $senderAccount) {
            $senderAccount = $senderAccounts->resolveForAudience($log->segment->audience ?? 'supplier');
        }

        if ($senderAccount instanceof OutreachSenderAccount && ! $senderAccount->is_active) {
            $log->forceFill([
                'status' => OutreachSendLog::STATUS_FAILED,
                'attempted_at' => now(),
                'error_message' => 'Assigned sender account is inactive.',
            ])->save();

            return;
        }

        $compiled = $renderer->render($template, $contact, $log->segment);
        $message = new OutreachPlainTextMail(
            $compiled['subject'],
            $compiled['body'],
            $compiled['unsubscribe_url'],
            $senderAccount?->from_email,
            $senderAccount?->from_name,
            $senderAccount?->reply_to_email,
        );

        $log->forceFill([
            'attempted_at' => now(),
            'subject' => $compiled['subject'],
            'body_text' => $compiled['body'],
            'sender_email' => $senderAccount?->from_email ?: (string) config('mail.from.address'),
        ])->save();

        try {
            $mailer->send($senderAccount, $contact->email, $message);

            $contact->forceFill([
                'sent_count' => $contact->sent_count + 1,
                'last_sent_at' => now(),
                'last_template_id' => $template->id,
                'next_template_step' => $contact->next_template_step + 1,
                'last_result' => 'sent',
            ])->save();

            $log->forceFill([
                'status' => OutreachSendLog::STATUS_SENT,
                'sent_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $contact->forceFill([
                'last_result' => 'failed',
            ])->save();

            $log->forceFill([
                'status' => OutreachSendLog::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $log = OutreachSendLog::query()->with('contact')->find($this->sendLogId);

        if (! $log) {
            return;
        }

        $log->forceFill([
            'status' => OutreachSendLog::STATUS_FAILED,
            'attempted_at' => $log->attempted_at ?? now(),
            'error_message' => $exception?->getMessage() ?: 'Outreach email job failed on the queue worker.',
        ])->save();

        if ($log->contact && $log->contact->last_result !== 'sent') {
            $log->contact->forceFill([
                'last_result' => 'failed',
            ])->save();
        }
    }
}
