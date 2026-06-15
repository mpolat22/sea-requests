<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\SupplierReview;
use App\Models\User;
use App\Notifications\MarketplaceNotification;
use Illuminate\Support\Facades\Notification;

class MarketplaceNotificationCenter
{
    public static function notifyRegistrationCreated(User $user): void
    {
        self::notifyAdmins(new MarketplaceNotification([
            'tone' => 'info',
            'action_url' => route('admin.dashboard'),
            'en' => [
                'subject' => 'Sea Requests | New User Registration',
                'title' => 'New User Registration',
                'message' => "{$user->name} ({$user->email}) has created a new {$user->role} account.",
                'action_label' => 'Review Registrations',
            ],
        ]));
    }

    public static function notifySellerVerificationSubmitted(User $user): void
    {
        $user->notify(new MarketplaceNotification([
            'tone' => 'success',
            'action_url' => route('approval.pending'),
            'en' => [
                'subject' => 'Sea Requests | Business Application Received',
                'title' => 'Business Application Received',
                'message' => 'We have received your business details and supporting documents.',
                'details' => [
                    ['label' => 'Next Step', 'value' => 'Your submission will be reviewed by our admin team before activation.'],
                ],
                'action_label' => 'View Application Status',
            ],
        ]));

        self::notifyAdmins(new MarketplaceNotification([
            'tone' => 'info',
            'action_url' => route('admin.dashboard'),
            'en' => [
                'subject' => 'Sea Requests | New Supplier Application',
                'title' => 'New Supplier Application',
                'message' => "A supplier verification submission has been received for {$user->company_name}.",
                'action_label' => 'Review Application',
            ],
        ]));
    }

    public static function notifySellerUpdateRequestSubmitted(User $user): void
    {
        $user->notify(new MarketplaceNotification([
            'tone' => 'success',
            'action_url' => route('seller.dashboard'),
            'en' => [
                'subject' => 'Sea Requests | Update Request Received',
                'title' => 'Update Request Received',
                'message' => 'The changes you submitted for your business profile are now under review.',
                'details' => [
                    ['label' => 'Next Step', 'value' => 'Your current live profile will remain visible until the update is reviewed and approved.'],
                ],
                'action_label' => 'Open Dashboard',
            ],
        ]));

        self::notifyAdmins(new MarketplaceNotification([
            'tone' => 'info',
            'action_url' => route('admin.dashboard'),
            'en' => [
                'subject' => 'Sea Requests | Supplier Update Request',
                'title' => 'Supplier Update Request',
                'message' => "A new business profile update request has been submitted for {$user->company_name}.",
                'action_label' => 'Review Request',
            ],
        ]));
    }

    public static function notifyApprovalDecision(User $user, string $status, array $feedback = []): void
    {
        $isApproved = $status === 'approved';
        $reasonLabel = self::rejectionReasonLabel($feedback['reason'] ?? null);
        $fieldLabel = self::rejectionFieldLabel($feedback['fields'] ?? []);
        $note = trim((string) ($feedback['note'] ?? ''));

        $details = [];

        if ($reasonLabel) {
            $details[] = ['label' => 'Reason', 'value' => $reasonLabel];
        }

        if ($fieldLabel !== '') {
            $details[] = ['label' => 'Fields to Update', 'value' => $fieldLabel];
        }

        if ($note !== '') {
            $details[] = ['label' => 'Explanation', 'value' => $note];
        }

        $user->notify(new MarketplaceNotification([
            'tone' => $isApproved ? 'success' : 'error',
            'action_url' => $isApproved ? route('dashboard') : route('seller.verification.create'),
            'en' => [
                'subject' => $isApproved ? 'Sea Requests | Application Approved' : 'Sea Requests | Application Rejected',
                'title' => $isApproved ? 'Application Approved' : 'Application Rejected',
                'message' => $isApproved
                    ? 'Your application has been approved. You may now access your account and start using the platform.'
                    : 'Your application was not approved at this time.',
                'details' => $details,
                'action_label' => $isApproved ? 'Open Dashboard' : 'Edit Application',
            ],
        ]));

        self::notifyAdmins(new MarketplaceNotification([
            'tone' => $isApproved ? 'success' : 'error',
            'action_url' => route('admin.dashboard'),
            'en' => [
                'subject' => $isApproved ? 'Sea Requests | Application Approved' : 'Sea Requests | Application Rejected',
                'title' => $isApproved ? 'Application Approved' : 'Application Rejected',
                'message' => $isApproved
                    ? "The supplier application for {$user->company_name} has been approved."
                    : "The supplier application for {$user->company_name} has been rejected.",
                'action_label' => 'View Applications',
            ],
        ]));
    }

    public static function notifySellerRemovalRequest(User $user): void
    {
        $reason = match ($user->seller_removal_request_reason) {
            'business_closed' => 'My business has closed',
            'duplicate_listing' => 'I created a duplicate listing',
            'wrong_account' => 'I applied with the wrong account',
            'not_needed' => 'I no longer want to be listed on the platform',
            default => 'Other',
        };

        self::notifyAdmins(new MarketplaceNotification([
            'tone' => 'error',
            'action_url' => route('admin.dashboard'),
            'en' => [
                'subject' => 'Sea Requests | Removal Request Submitted',
                'title' => 'Removal Request Submitted',
                'message' => "A business removal request has been submitted for {$user->company_name}.",
                'details' => [
                    ['label' => 'Reason', 'value' => $reason],
                    ['label' => 'Explanation', 'value' => (string) $user->seller_removal_request_note],
                ],
                'action_label' => 'Review Request',
            ],
        ]));
    }

    public static function notifySellerRemovalRequestReviewed(User $user, bool $approved, string $note = ''): void
    {
        $details = [];

        if ($note !== '') {
            $details[] = ['label' => 'Explanation', 'value' => $note];
        }

        $user->notify(new MarketplaceNotification([
            'tone' => $approved ? 'success' : 'error',
            'action_url' => route('seller.dashboard'),
            'en' => [
                'subject' => $approved ? 'Sea Requests | Removal Request Approved' : 'Sea Requests | Removal Request Rejected',
                'title' => $approved ? 'Removal Request Approved' : 'Removal Request Rejected',
                'message' => $approved
                    ? 'Your business removal request has been approved. Your user account remains active in the system.'
                    : 'Your business removal request was not approved at this time.',
                'details' => $details,
                'action_label' => 'Open Dashboard',
            ],
        ]));
    }

    public static function notifySellerUpdateRequestReviewed(User $user, bool $approved, array $feedback = []): void
    {
        $reasonLabel = self::rejectionReasonLabel($feedback['reason'] ?? null);
        $fieldLabel = self::rejectionFieldLabel($feedback['fields'] ?? []);
        $note = trim((string) ($feedback['note'] ?? ''));

        $details = [];

        if ($reasonLabel) {
            $details[] = ['label' => 'Reason', 'value' => $reasonLabel];
        }

        if ($fieldLabel !== '') {
            $details[] = ['label' => 'Fields to Update', 'value' => $fieldLabel];
        }

        if ($note !== '') {
            $details[] = ['label' => 'Explanation', 'value' => $note];
        }

        $user->notify(new MarketplaceNotification([
            'tone' => $approved ? 'success' : 'error',
            'action_url' => route('seller.verification.create'),
            'en' => [
                'subject' => $approved ? 'Sea Requests | Update Approved' : 'Sea Requests | Update Rejected',
                'title' => $approved ? 'Update Approved' : 'Update Rejected',
                'message' => $approved
                    ? 'The update you submitted for your business profile has been approved and is now live.'
                    : 'The update you submitted for your business profile was not approved at this time.',
                'details' => $details,
                'action_label' => $approved ? 'View Business Profile' : 'Edit Update Request',
            ],
        ]));
    }

    public static function notifySupplierReviewReceived(User $seller, User $buyer, Offer $offer, int $rating): void
    {
        $buyerCompany = $buyer->company_name ?: $buyer->name;

        $seller->notify(new MarketplaceNotification([
            'tone' => 'info',
            'action_url' => route('seller.reviews'),
            'en' => [
                'subject' => 'Sea Requests | New Buyer Review Received',
                'title' => 'New Buyer Review Received',
                'message' => "A new buyer review has been published for {$seller->company_name}.",
                'details' => [
                    ['label' => 'Buyer Company', 'value' => $buyerCompany],
                    ['label' => 'Reference No', 'value' => (string) ($offer->rfq?->reference_no ?? '-')],
                    ['label' => 'Rating', 'value' => "{$rating}/5"],
                ],
                'action_label' => 'Open Reviews',
            ],
        ]));
    }

    public static function notifyBuyerReviewReplyReceived(User $buyer, User $seller, SupplierReview $review): void
    {
        $sellerName = $seller->company_name ?: $seller->name;

        $buyer->notify(new MarketplaceNotification([
            'tone' => 'info',
            'action_url' => ServiceRoute::firstProfileUrl($seller, [
                'tab' => 'reviews',
                'review_offer' => $review->offer_id,
            ]),
            'en' => [
                'subject' => 'Sea Requests | Supplier Reply Received',
                'title' => 'Supplier Reply Received',
                'message' => "The supplier {$sellerName} replied to your review.",
                'details' => [
                    ['label' => 'Supplier', 'value' => $sellerName],
                    ['label' => 'Reference No', 'value' => (string) ($review->rfq?->reference_no ?? '-')],
                ],
                'action_label' => 'Open Review',
            ],
        ]));
    }

    private static function rejectionReasonLabel(?string $reason): ?string
    {
        return match ($reason) {
            'documents_incomplete' => 'Documents are incomplete or insufficient',
            'information_mismatch' => 'The submitted information does not match',
            'service_scope_unclear' => 'The service scope is unclear',
            'compliance_issue' => 'There is a compliance or verification issue',
            'other' => 'Other',
            default => null,
        };
    }

    private static function rejectionFieldLabel(array $fields): string
    {
        $labels = [
            'company_name' => 'Business name',
            'service_category_ids' => 'Category and subcategory',
            'service_brand_ids' => 'Brands',
            'service_country_codes' => 'Service countries',
            'service_ports_by_country' => 'Service ports',
            'country' => 'Country',
            'company_city' => 'City',
            'company_address_line' => 'Address',
            'phone' => 'Phone',
            'company_overview' => 'Company overview',
            'registration_number' => 'Registration number',
            'company_logo' => 'Logo',
            'official_documents' => 'Official documents',
        ];

        return collect($fields)
            ->map(fn ($field) => $labels[$field] ?? null)
            ->filter()
            ->implode(', ');
    }

    private static function notifyAdmins(MarketplaceNotification $notification): void
    {
        $admins = User::adminRecipients();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, $notification);
    }
}
