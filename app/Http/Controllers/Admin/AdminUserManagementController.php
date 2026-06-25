<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MarketplaceNotificationCenter;
use App\Support\SupplierServiceListingIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserManagementController extends Controller
{
    public function updateProfile(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_if($user->isAdmin(), 403);

        $previousRole = $user->role;
        $normalizedEmail = strtolower(trim((string) $request->input('email')));

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:buyer,seller'],
            'company_name' => [Rule::requiredIf($request->input('role') === 'seller'), 'nullable', 'string', 'min:2', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'company_description' => ['nullable', 'string', 'max:2000'],
            'email_verified' => ['required', 'boolean'],
        ]);

        $emailChanged = $normalizedEmail !== strtolower((string) $user->email);
        $emailVerified = (bool) $validated['email_verified'];
        $emailVerifiedAt = $emailVerified
            ? (($emailChanged || blank($user->email_verified_at)) ? now() : $user->email_verified_at)
            : null;

        $user->forceFill([
            'name' => trim((string) $validated['name']),
            'email' => $normalizedEmail,
            'role' => $validated['role'],
            'company_name' => filled($validated['company_name'] ?? null) ? trim((string) $validated['company_name']) : null,
            'country' => trim((string) $validated['country']),
            'countries' => trim((string) $validated['country']),
            'phone' => trim((string) $validated['phone']),
            'whatsapp_number' => filled($validated['whatsapp_number'] ?? null) ? trim((string) $validated['whatsapp_number']) : null,
            'company_description' => filled($validated['company_description'] ?? null) ? trim((string) $validated['company_description']) : null,
            'email_verified_at' => $emailVerifiedAt,
            'locale' => 'en',
        ])->save();

        if ($previousRole !== $user->role) {
            if ($user->isSeller() && $user->isApproved()) {
                app(SupplierServiceListingIndex::class)->syncSeller($user);
            } else {
                app(SupplierServiceListingIndex::class)->clearSeller($user);
            }
        }

        return back()->with('success', 'admin-user-updated');
    }

    public function updateBusiness(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->isSeller(), 404);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'company_city' => ['nullable', 'string', 'max:120'],
            'company_address_line' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
        ]);

        $user->forceFill([
            'company_name' => trim((string) $validated['company_name']),
            'country' => filled($validated['country'] ?? null) ? trim((string) $validated['country']) : null,
            'company_city' => filled($validated['company_city'] ?? null) ? trim((string) $validated['company_city']) : null,
            'company_address_line' => filled($validated['company_address_line'] ?? null) ? trim((string) $validated['company_address_line']) : null,
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'contact_email' => filled($validated['contact_email'] ?? null) ? trim((string) $validated['contact_email']) : null,
            'website_url' => filled($validated['website_url'] ?? null) ? trim((string) $validated['website_url']) : null,
        ])->save();

        if ($user->isApproved()) {
            app(SupplierServiceListingIndex::class)->syncSeller($user);
        }

        return back()->with('success', 'admin-business-updated');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_if($user->isAdmin(), 403);

        $user->servicePorts()->detach();
        $user->delete();

        return back()->with('success', 'admin-user-deleted');
    }

    public function destroyBusiness(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->isSeller(), 404);

        $this->clearBusinessRecord($user);

        return back()->with('success', 'admin-business-deleted');
    }

    public function reviewRemovalRequest(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->isSeller(), 404);

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'min:10', 'max:1000'],
        ]);

        if ($validated['action'] === 'reject') {
            $request->validate([
                'note' => ['required', 'string', 'min:10', 'max:1000'],
            ]);
        }

        if ($validated['action'] === 'approve') {
            $this->clearBusinessRecord($user);
            MarketplaceNotificationCenter::notifySellerRemovalRequestReviewed($user, true);

            return back()->with('success', 'seller-removal-request-reviewed');
        }

        MarketplaceNotificationCenter::notifySellerRemovalRequestReviewed($user, false, trim((string) ($validated['note'] ?? '')));

        $user->forceFill([
            'seller_removal_request_reason' => null,
            'seller_removal_request_note' => null,
            'seller_removal_request_status' => null,
            'seller_removal_requested_at' => null,
        ])->save();

        return back()->with('success', 'seller-removal-request-reviewed');
    }

    private function clearBusinessRecord(User $user): void
    {
        $user->servicePorts()->detach();

        $user->forceFill([
            'company_name' => null,
            'phone' => null,
            'country' => null,
            'countries' => null,
            'whatsapp_number' => null,
            'company_description' => null,
            'company_overview' => null,
            'operating_regions' => null,
            'port_coverage' => null,
            'service_country_codes' => [],
            'company_address' => null,
            'company_address_line' => null,
            'company_city' => null,
            'company_district' => null,
            'company_neighborhood' => null,
            'company_state' => null,
            'company_postal_code' => null,
            'company_location_name' => null,
            'company_latitude' => null,
            'company_longitude' => null,
            'registration_number' => null,
            'website_url' => null,
            'landline_phone' => null,
            'contact_email' => null,
            'instagram_url' => null,
            'linkedin_url' => null,
            'facebook_url' => null,
            'twitter_url' => null,
            'telegram_url' => null,
            'company_registration_document_path' => null,
            'tax_certificate_document_path' => null,
            'service_authorization_document_path' => null,
            'company_logo_path' => null,
            'company_cover_path' => null,
            'company_registration_documents' => [],
            'tax_certificate_documents' => [],
            'service_authorization_documents' => [],
            'company_gallery' => [],
            'service_category_ids' => [],
            'service_subcategory_ids' => [],
            'service_subcategories_by_category' => [],
            'seller_verification_submitted_at' => null,
            'seller_verification_onboarding_sent_at' => null,
            'seller_verification_24h_reminder_sent_at' => null,
            'seller_verification_72h_reminder_sent_at' => null,
            'seller_rejection_reason' => null,
            'seller_rejection_note' => null,
            'seller_rejection_fields' => null,
            'seller_rejected_at' => null,
            'seller_update_request_status' => null,
            'seller_update_request_payload' => null,
            'seller_update_request_diff' => null,
            'seller_update_requested_at' => null,
            'seller_update_rejection_reason' => null,
            'seller_update_rejection_note' => null,
            'seller_update_rejection_fields' => null,
            'seller_update_rejected_at' => null,
            'seller_removal_request_reason' => null,
            'seller_removal_request_note' => null,
            'seller_removal_request_status' => null,
            'seller_removal_requested_at' => null,
            'approval_status' => 'pending',
            'approved_at' => null,
        ])->save();

        app(SupplierServiceListingIndex::class)->clearSeller($user);
    }
}
