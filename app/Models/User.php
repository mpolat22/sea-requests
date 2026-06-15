<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Notifications\AccountApprovalStatusNotification;
use App\Notifications\CustomResetPasswordNotification;
use App\Notifications\CustomVerifyEmailNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_name',
        'phone',
        'country',
        'countries',
        'whatsapp_number',
        'company_description',
        'company_overview',
        'operating_regions',
        'port_coverage',
        'service_country_codes',
        'company_address',
        'company_address_line',
        'company_city',
        'company_district',
        'company_neighborhood',
        'company_state',
        'company_postal_code',
        'company_location_name',
        'company_latitude',
        'company_longitude',
        'registration_number',
        'website_url',
        'landline_phone',
        'contact_email',
        'instagram_url',
        'linkedin_url',
        'facebook_url',
        'twitter_url',
        'telegram_url',
        'company_registration_document_path',
        'tax_certificate_document_path',
        'service_authorization_document_path',
        'company_logo_path',
        'company_cover_path',
        'company_registration_documents',
        'tax_certificate_documents',
        'service_authorization_documents',
        'company_gallery',
        'service_category_ids',
        'service_subcategory_ids',
        'service_subcategories_by_category',
        'service_brand_ids',
        'seller_verification_submitted_at',
        'seller_rejection_reason',
        'seller_rejection_note',
        'seller_rejection_fields',
        'seller_rejected_at',
        'seller_update_request_status',
        'seller_update_request_payload',
        'seller_update_request_diff',
        'seller_update_requested_at',
        'seller_update_rejection_reason',
        'seller_update_rejection_note',
        'seller_update_rejection_fields',
        'seller_update_rejected_at',
        'seller_removal_request_reason',
        'seller_removal_request_note',
        'seller_removal_request_status',
        'seller_removal_requested_at',
        'email',
        'locale',
        'role',
        'approval_status',
        'approved_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'seller_verification_submitted_at' => 'datetime',
            'seller_rejected_at' => 'datetime',
            'seller_update_request_payload' => 'array',
            'seller_update_request_diff' => 'array',
            'seller_update_requested_at' => 'datetime',
            'seller_update_rejection_fields' => 'array',
            'seller_update_rejected_at' => 'datetime',
            'seller_removal_requested_at' => 'datetime',
            'company_registration_documents' => 'array',
            'tax_certificate_documents' => 'array',
            'service_authorization_documents' => 'array',
            'company_gallery' => 'array',
            'service_category_ids' => 'array',
            'service_subcategory_ids' => 'array',
            'service_subcategories_by_category' => 'array',
            'service_brand_ids' => 'array',
            'service_country_codes' => 'array',
            'seller_rejection_fields' => 'array',
            'password' => 'hashed',
        ];
    }

    public function servicePorts(): BelongsToMany
    {
        return $this->belongsToMany(Port::class, 'seller_service_ports')
            ->withTimestamps();
    }

    public function serviceListings(): HasMany
    {
        return $this->hasMany(SupplierServiceListing::class, 'seller_id');
    }

    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class, 'buyer_id');
    }

    public function writtenReviews(): HasMany
    {
        return $this->hasMany(SupplierReview::class, 'buyer_id');
    }

    public function receivedReviews(): HasMany
    {
        return $this->hasMany(SupplierReview::class, 'seller_id');
    }

    public function rfqImportTemplate(): HasOne
    {
        return $this->hasOne(RfqImportTemplate::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function isBuyer(): bool
    {
        return $this->role === 'buyer';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function requiresSellerVerification(): bool
    {
        return $this->isSeller();
    }

    public function hasSubmittedSellerVerification(): bool
    {
        $hasOfficialDocument = filled($this->company_registration_documents)
            || filled($this->tax_certificate_documents)
            || filled($this->service_authorization_documents);

        return $this->seller_verification_submitted_at !== null
            && filled($this->company_name)
            && filled($this->company_address_line)
            && filled($this->company_city)
            && filled($this->country)
            && filled($this->phone)
            && filled($this->contact_email)
            && filled($this->company_overview)
            && filled($this->company_logo_path)
            && filled($this->service_category_ids)
            && filled($this->service_country_codes)
            && $this->servicePorts()->exists()
            && filled($this->registration_number)
            && $hasOfficialDocument;
    }

    public function hasPendingSellerUpdateRequest(): bool
    {
        return $this->seller_update_request_status === 'pending' && is_array($this->seller_update_request_payload);
    }

    public function preferredLocale(): string
    {
        return 'en';
    }

    /**
     * @return Collection<int, static>
     */
    public static function adminRecipients(): Collection
    {
        return static::query()
            ->where('role', 'admin')
            ->orderBy('id')
            ->get();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomVerifyEmailNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    public function sendApprovalStatusNotification(): void
    {
        $this->notify(new AccountApprovalStatusNotification($this->approval_status));
    }
}
