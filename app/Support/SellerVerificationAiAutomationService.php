<?php

namespace App\Support;

use App\Models\User;

class SellerVerificationAiAutomationService
{
    public function __construct(
        private readonly SellerVerificationAiReviewService $reviewService,
    ) {
    }

    public function processIfStillPending(int $userId, string $submittedAtIso): ?array
    {
        $user = User::query()->find($userId);

        if (! $user || ! $user->isSeller()) {
            return null;
        }

        if ($user->approval_status !== 'pending' || ! $user->seller_verification_submitted_at) {
            return null;
        }

        if ($user->seller_verification_ai_reviewed_at) {
            return null;
        }

        $currentSubmittedAtIso = $user->seller_verification_submitted_at?->toISOString();

        if (! $currentSubmittedAtIso || $currentSubmittedAtIso !== $submittedAtIso) {
            return null;
        }

        if ($user->seller_verification_submitted_at->gt(now()->subHour())) {
            return null;
        }

        $reviewOutcome = $this->reviewService->review($user, $this->verificationPayloadFromUser($user));
        $decision = $reviewOutcome['decision'] ?? 'manual_review';
        $approvalStatus = match ($decision) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => 'pending',
        };

        $user->forceFill([
            'approval_status' => $approvalStatus,
            'approved_at' => $approvalStatus === 'approved' ? ($user->approved_at ?: now()) : null,
            'seller_rejection_reason' => $approvalStatus === 'rejected' ? ($reviewOutcome['rejection_reason'] ?? null) : null,
            'seller_rejection_note' => $approvalStatus === 'rejected' ? ($reviewOutcome['rejection_note'] ?? null) : null,
            'seller_rejection_fields' => $approvalStatus === 'rejected'
                ? array_values(array_unique($reviewOutcome['rejection_fields'] ?? []))
                : null,
            'seller_rejected_at' => $approvalStatus === 'rejected' ? now() : null,
            'seller_verification_ai_review' => is_array($reviewOutcome['review'] ?? null) ? $reviewOutcome['review'] : null,
            'seller_verification_ai_reviewed_at' => now(),
        ])->save();

        if ($user->isApproved()) {
            app(SupplierServiceListingIndex::class)->syncSeller($user);
        } else {
            app(SupplierServiceListingIndex::class)->clearSeller($user);
        }

        if ($decision === 'approve') {
            MarketplaceNotificationCenter::notifyApprovalDecision($user, 'approved');
        } elseif ($decision === 'reject') {
            MarketplaceNotificationCenter::notifyApprovalDecision($user, 'rejected', [
                'reason' => $reviewOutcome['rejection_reason'] ?? null,
                'fields' => $reviewOutcome['rejection_fields'] ?? [],
                'note' => $reviewOutcome['rejection_note'] ?? null,
            ]);
        }

        return $reviewOutcome;
    }

    private function verificationPayloadFromUser(User $user): array
    {
        return [
            'company_name' => $user->company_name,
            'phone' => $user->phone,
            'landline_phone' => $user->landline_phone,
            'contact_email' => $user->contact_email ?? $user->email,
            'whatsapp_number' => $user->whatsapp_number,
            'instagram_url' => $user->instagram_url,
            'linkedin_url' => $user->linkedin_url,
            'facebook_url' => $user->facebook_url,
            'twitter_url' => $user->twitter_url,
            'telegram_url' => $user->telegram_url,
            'country' => $user->country ?: collect(explode(',', (string) $user->countries))
                ->map(fn (string $country) => trim($country))
                ->filter()
                ->first(),
            'company_address_line' => $user->company_address_line,
            'company_city' => $user->company_city,
            'company_district' => $user->company_district,
            'company_neighborhood' => $user->company_neighborhood,
            'company_postal_code' => $user->company_postal_code,
            'company_overview' => $user->company_overview ?? $user->company_description,
            'port_coverage' => $user->port_coverage,
            'registration_number' => $user->registration_number,
            'website_url' => $user->website_url,
            'service_category_ids' => $user->service_category_ids ?? [],
            'service_subcategory_ids' => $user->service_subcategory_ids ?? [],
            'service_subcategories_by_category' => $user->service_subcategories_by_category ?? [],
            'service_brand_ids' => $user->service_brand_ids ?? [],
            'service_country_codes' => $user->service_country_codes ?? [],
            'service_ports_by_country' => $user->servicePorts()
                ->get(['ports.id', 'ports.country_code'])
                ->groupBy('country_code')
                ->map(fn ($ports) => $ports->pluck('id')->map(fn ($id) => (int) $id)->values()->all())
                ->toArray(),
            'company_logo' => $this->singleFile($user->company_logo_path),
            'company_registration_documents' => $this->documentSet($user->company_registration_documents),
        ];
    }

    private function singleFile(?string $path): ?array
    {
        if (! filled($path)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => basename($path),
        ];
    }

    private function documentSet(array|string|null $documents): array
    {
        return collect(is_array($documents) ? $documents : [$documents])
            ->map(function ($document) {
                if (is_array($document) && filled($document['path'] ?? null)) {
                    $document['name'] = $document['name'] ?? basename((string) $document['path']);

                    return $document;
                }

                if (is_string($document) && filled($document)) {
                    return [
                        'path' => $document,
                        'name' => basename($document),
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }
}
