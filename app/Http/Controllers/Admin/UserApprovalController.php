<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MarketplaceNotificationCenter;
use App\Support\SupplierServiceListingIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserApprovalController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string', 'in:documents_incomplete,information_mismatch,service_scope_unclear,compliance_issue,other'],
            'rejection_note' => ['nullable', 'string', 'min:10', 'max:1000'],
            'rejection_fields' => ['nullable', 'array'],
            'rejection_fields.*' => ['string', 'in:company_name,service_category_ids,service_brand_ids,service_country_codes,service_ports_by_country,country,company_city,company_district,company_neighborhood,company_postal_code,company_address_line,phone,landline_phone,contact_email,website_url,whatsapp_number,telegram_url,instagram_url,linkedin_url,facebook_url,twitter_url,company_overview,port_coverage,registration_number,company_logo,company_registration_documents'],
        ]);

        if ($user->isSeller() && $validated['action'] === 'approve' && ! $user->hasSubmittedSellerVerification()) {
            return back()->with('error', 'seller-verification-required');
        }

        $hasPendingUpdateRequest = $user->hasPendingSellerUpdateRequest();

        if ($validated['action'] === 'reject') {
            $request->validate([
                'rejection_reason' => ['required', 'string', 'in:documents_incomplete,information_mismatch,service_scope_unclear,compliance_issue,other'],
                'rejection_note' => ['required', 'string', 'min:10', 'max:1000'],
                'rejection_fields' => ['required', 'array', 'min:1'],
            ]);
        }

        if ($hasPendingUpdateRequest && $validated['action'] === 'approve') {
            $payload = $user->seller_update_request_payload ?? [];
            $this->applyApprovedUpdateRequest($user, $payload);

            MarketplaceNotificationCenter::notifySellerUpdateRequestReviewed($user, true);

            return back()->with('success', 'seller-update-request-reviewed');
        }

        if ($hasPendingUpdateRequest && $validated['action'] === 'reject') {
            $rejectionFields = array_values(array_unique($validated['rejection_fields'] ?? []));

            $user->forceFill([
                'seller_update_request_status' => null,
                'seller_update_request_payload' => null,
                'seller_update_request_diff' => null,
                'seller_update_requested_at' => null,
                'seller_update_rejection_reason' => $validated['rejection_reason'],
                'seller_update_rejection_note' => trim((string) $validated['rejection_note']),
                'seller_update_rejection_fields' => $rejectionFields,
                'seller_update_rejected_at' => now(),
            ])->save();

            MarketplaceNotificationCenter::notifySellerUpdateRequestReviewed($user, false, [
                'reason' => $user->seller_update_rejection_reason,
                'note' => $user->seller_update_rejection_note,
                'fields' => $user->seller_update_rejection_fields ?? [],
            ]);

            return back()->with('success', 'seller-update-request-reviewed');
        }

        $isApproved = $validated['action'] === 'approve';
        $rejectionFields = array_values(array_unique($validated['rejection_fields'] ?? []));

        $user->forceFill([
            'approval_status' => $isApproved ? 'approved' : 'rejected',
            'approved_at' => $isApproved ? now() : null,
            'seller_rejection_reason' => $isApproved ? null : ($validated['rejection_reason'] ?? null),
            'seller_rejection_note' => $isApproved ? null : trim((string) ($validated['rejection_note'] ?? '')),
            'seller_rejection_fields' => $isApproved ? null : $rejectionFields,
            'seller_rejected_at' => $isApproved ? null : now(),
        ])->save();

        if ($isApproved) {
            app(SupplierServiceListingIndex::class)->syncSeller($user);
        } else {
            app(SupplierServiceListingIndex::class)->clearSeller($user);
        }

        MarketplaceNotificationCenter::notifyApprovalDecision($user, $user->approval_status, [
            'reason' => $user->seller_rejection_reason,
            'note' => $user->seller_rejection_note,
            'fields' => $user->seller_rejection_fields ?? [],
        ]);

        return back()->with('success', 'approval-updated');
    }

    private function applyApprovedUpdateRequest(User $user, array $payload): void
    {
        $address = collect([
            $payload['company_address_line'] ?? null,
            $payload['company_neighborhood'] ?? null,
            $payload['company_district'] ?? null,
            $payload['company_city'] ?? null,
            $payload['company_postal_code'] ?? null,
            $payload['country'] ?? null,
        ])->filter()->implode(', ');

        $companyRegistrationDocuments = $payload['company_registration_documents'] ?? [];

        $user->forceFill([
            'company_name' => $payload['company_name'] ?? $user->company_name,
            'country' => $payload['country'] ?? $user->country,
            'countries' => $payload['country'] ?? $user->countries,
            'phone' => $payload['phone'] ?? $user->phone,
            'landline_phone' => $payload['landline_phone'] ?? null,
            'contact_email' => $payload['contact_email'] ?? null,
            'whatsapp_number' => $payload['whatsapp_number'] ?? null,
            'instagram_url' => $payload['instagram_url'] ?? null,
            'linkedin_url' => $payload['linkedin_url'] ?? null,
            'facebook_url' => $payload['facebook_url'] ?? null,
            'twitter_url' => $payload['twitter_url'] ?? null,
            'telegram_url' => $payload['telegram_url'] ?? null,
            'company_description' => $payload['company_overview'] ?? null,
            'company_overview' => $payload['company_overview'] ?? null,
            'port_coverage' => $payload['port_coverage'] ?? null,
            'service_country_codes' => $payload['service_country_codes'] ?? [],
            'company_address' => $address,
            'company_address_line' => $payload['company_address_line'] ?? null,
            'company_city' => $payload['company_city'] ?? null,
            'company_district' => $payload['company_district'] ?? null,
            'company_neighborhood' => $payload['company_neighborhood'] ?? null,
            'company_postal_code' => $payload['company_postal_code'] ?? null,
            'registration_number' => $payload['registration_number'] ?? null,
            'website_url' => $payload['website_url'] ?? null,
            'service_category_ids' => $payload['service_category_ids'] ?? [],
            'service_subcategory_ids' => $payload['service_subcategory_ids'] ?? [],
            'service_subcategories_by_category' => $payload['service_subcategories_by_category'] ?? [],
            'service_brand_ids' => $payload['service_brand_ids'] ?? [],
            'company_logo_path' => $payload['company_logo']['path'] ?? $user->company_logo_path,
            'company_registration_documents' => $companyRegistrationDocuments,
            'tax_certificate_documents' => [],
            'service_authorization_documents' => [],
            'company_registration_document_path' => $companyRegistrationDocuments[0]['path'] ?? null,
            'tax_certificate_document_path' => null,
            'service_authorization_document_path' => null,
            'approval_status' => 'approved',
            'approved_at' => $user->approved_at ?: now(),
            'seller_update_request_status' => null,
            'seller_update_request_payload' => null,
            'seller_update_request_diff' => null,
            'seller_update_requested_at' => null,
            'seller_update_rejection_reason' => null,
            'seller_update_rejection_note' => null,
            'seller_update_rejection_fields' => null,
            'seller_update_rejected_at' => null,
        ])->save();

        $user->servicePorts()->sync(
            collect($payload['service_ports_by_country'] ?? [])
                ->flatten()
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->unique()
                ->values()
                ->all()
        );

        app(SupplierServiceListingIndex::class)->syncSeller($user);
    }
}
