<?php

namespace App\Support\Outreach;

use App\Models\OutreachSendLog;
use App\Models\OutreachSenderAccount;
use Illuminate\Support\Facades\DB;

class OutreachSenderAccountManager
{
    public function resolveForAudience(string $audience): ?OutreachSenderAccount
    {
        $query = OutreachSenderAccount::query()->where('audience', $audience);

        if (! $query->exists()) {
            return null;
        }

        return (clone $query)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function store(array $attributes): OutreachSenderAccount
    {
        return DB::transaction(function () use ($attributes) {
            $hasExistingDefault = OutreachSenderAccount::query()
                ->where('audience', $attributes['audience'])
                ->where('is_default', true)
                ->exists();

            if (! $hasExistingDefault && ($attributes['is_active'] ?? false)) {
                $attributes['is_default'] = true;
            }

            if (($attributes['is_default'] ?? false) === true) {
                $attributes['is_active'] = true;
            }

            $account = OutreachSenderAccount::query()->create($attributes);

            $this->syncDefaults($account);

            return $account;
        });
    }

    public function update(OutreachSenderAccount $account, array $attributes): OutreachSenderAccount
    {
        return DB::transaction(function () use ($account, $attributes) {
            if (($attributes['is_default'] ?? false) === true) {
                $attributes['is_active'] = true;
            }

            if (($attributes['is_active'] ?? $account->is_active) === false) {
                $attributes['is_default'] = false;
            }

            $account->fill($attributes);
            $account->save();

            $this->syncDefaults($account);

            return $account->fresh();
        });
    }

    public function destroy(OutreachSenderAccount $account): void
    {
        DB::transaction(function () use ($account) {
            $audience = $account->audience;
            $wasDefault = $account->is_default;

            $account->delete();

            if ($wasDefault) {
                $this->promoteFallbackDefault($audience);
            }
        });
    }

    public function canDelete(OutreachSenderAccount $account): bool
    {
        return ! OutreachSendLog::query()
            ->where('sender_account_id', $account->id)
            ->where('status', OutreachSendLog::STATUS_QUEUED)
            ->exists();
    }

    private function syncDefaults(OutreachSenderAccount $account): void
    {
        if ($account->is_default) {
            OutreachSenderAccount::query()
                ->where('audience', $account->audience)
                ->whereKeyNot($account->id)
                ->update(['is_default' => false]);

            return;
        }

        $hasDefault = OutreachSenderAccount::query()
            ->where('audience', $account->audience)
            ->where('is_default', true)
            ->exists();

        if (! $hasDefault) {
            $this->promoteFallbackDefault($account->audience);
        }
    }

    private function promoteFallbackDefault(string $audience): void
    {
        $fallback = OutreachSenderAccount::query()
            ->where('audience', $audience)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $fallback) {
            return;
        }

        OutreachSenderAccount::query()
            ->where('audience', $audience)
            ->whereKeyNot($fallback->id)
            ->update(['is_default' => false]);

        $fallback->forceFill(['is_default' => true])->save();
    }
}
