<?php

namespace App\Support\Onboarding;

use App\Models\OutreachContact;
use App\Models\User;

class OnboardingDraftCreator
{
    /**
     * @param array<string, mixed> $parsed
     * @return array{result:string,contact:?OutreachContact}
     */
    public function create(
        string $audience,
        array $parsed,
        string $rawProfile,
        string $sourceName,
        string $createdFrom,
        ?int $adminId = null,
        ?string $profileUrl = null,
        ?int $categoryImportId = null,
    ): array {
        $email = strtolower((string) ($parsed['email'] ?? ''));

        if (blank($email)) {
            return ['result' => 'failed', 'contact' => null];
        }

        $existingUser = User::query()->where('email', $email)->first();
        $contact = OutreachContact::query()->firstOrNew(['email' => $email]);
        $payload = $contact->source_payload ?? [];

        $alreadyImported = $contact->exists
            || ($profileUrl && data_get($payload, 'profile_url') === $profileUrl)
            || data_get($payload, 'created_from') === $createdFrom
            || $existingUser !== null;

        $contact->fill([
            'audience' => in_array($audience, ['seller', 'buyer'], true) ? $audience : 'seller',
            'organization_name' => $parsed['company_name'] ?: $contact->organization_name,
            'source_name' => $sourceName,
            'status' => $existingUser ? OutreachContact::STATUS_REGISTERED : OutreachContact::STATUS_ACTIVE,
            'notes' => $payload['notes'] ?? null,
            'source_payload' => array_merge($payload, [
                'onboarding_status' => $existingUser ? 'account_created' : ($payload['onboarding_status'] ?? 'draft'),
                'parsed' => array_merge($payload['parsed'] ?? [], $parsed),
                'user_id' => $existingUser?->id ?? data_get($payload, 'user_id'),
                'raw_profile' => $rawProfile,
                'profile_url' => $profileUrl ?: data_get($payload, 'profile_url'),
                'category_import_id' => $categoryImportId ?: data_get($payload, 'category_import_id'),
                'created_from' => $createdFrom,
                'created_by_admin_id' => $adminId ?? data_get($payload, 'created_by_admin_id'),
            ]),
        ])->save();

        return [
            'result' => $alreadyImported ? 'duplicate' : 'draft',
            'contact' => $contact,
        ];
    }
}
