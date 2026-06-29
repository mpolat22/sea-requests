<?php
namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Port;
use App\Models\Subcategory;
use App\Support\MarketplaceNotificationCenter;
use App\Support\SupplierServiceListingIndex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SellerVerificationController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->hasSubmittedSellerVerification() && $user->approval_status === 'pending') {
            return redirect()->route('approval.pending');
        }

        if ($user->seller_update_request_status === 'pending') {
            return redirect()->route('dashboard.seller')->with('success', 'seller-update-request-pending-lock');
        }

        return $this->renderForm($request, $user, false);
    }

    public function createForAdmin(Request $request, \App\Models\User $user): Response|RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->isSeller(), 404);

        return $this->renderForm($request, $user, true);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        return $this->persistVerification($request, $user, false);
    }

    public function storeForAdmin(Request $request, \App\Models\User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->isSeller(), 404);

        return $this->persistVerification($request, $user, true);
    }

    private function renderForm(Request $request, \App\Models\User $user, bool $adminMode): Response
    {
        $serviceCountries = Port::query()
            ->active()
            ->select('country_code', 'country_name')
            ->distinct()
            ->orderBy('country_code')
            ->get()
            ->map(fn (Port $port) => [
                'code' => $port->country_code,
                'name' => $port->country_name,
            ])
            ->values();

        $portsByCountry = Port::query()
            ->active()
            ->orderBy('country_code')
            ->orderBy('port_name')
            ->get(['id', 'country_code', 'country_name', 'port_name', 'unlocode'])
            ->groupBy('country_code')
            ->map(fn ($ports) => $ports
                ->map(fn (Port $port) => [
                    'id' => $port->id,
                    'country_code' => $port->country_code,
                    'country_name' => $port->country_name,
                    'port_name' => $port->port_name,
                    'unlocode' => $port->unlocode,
                ])
                ->values())
            ->toArray();

        $verification = $this->verificationDataFromUser($user);

        if ($user->seller_update_request_status === 'pending' && is_array($user->seller_update_request_payload)) {
            $verification = array_merge($verification, $user->seller_update_request_payload);
        }

        $rejectionFeedback = [
            'reason' => $user->seller_rejection_reason,
            'note' => $user->seller_rejection_note,
            'fields' => $user->seller_rejection_fields ?? [],
            'rejected_at' => optional($user->seller_rejected_at)?->toISOString(),
        ];

        if (! $adminMode && $user->seller_update_rejected_at) {
            $rejectionFeedback = [
                'reason' => $user->seller_update_rejection_reason,
                'note' => $user->seller_update_rejection_note,
                'fields' => $user->seller_update_rejection_fields ?? [],
                'rejected_at' => optional($user->seller_update_rejected_at)?->toISOString(),
            ];
        }

        return Inertia::render('Auth/SupplierVerification', [
            'categories' => Category::query()
                ->with('subcategories:id,category_id,name,slug')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'subcategories' => $category->subcategories
                        ->map(fn (Subcategory $subcategory) => [
                            'id' => $subcategory->id,
                            'name' => $subcategory->name,
                            'slug' => $subcategory->slug,
                        ])
                        ->values(),
                ])
                ->values(),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Brand $brand) => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                ])
                ->values(),
            'serviceCountries' => $serviceCountries,
            'portsByCountry' => $portsByCountry,
            'adminContext' => [
                'enabled' => $adminMode,
                'targetUserId' => $user->id,
                'targetUserName' => $user->company_name ?: $user->name,
                'returnUrl' => route('admin.dashboard'),
            ],
            'actionUrls' => [
                'submit' => $adminMode
                    ? route('admin.seller-verification.update', $user)
                    : route('seller.verification.store'),
                'removalRequest' => route('seller.verification.removal-request'),
            ],
            'verification' => [
                ...$verification,
                'approval_status' => $user->approval_status,
                'rejection_feedback' => $rejectionFeedback,
                'removal_request' => [
                    'reason' => $user->seller_removal_request_reason,
                    'note' => $user->seller_removal_request_note,
                    'status' => $user->seller_removal_request_status,
                    'requested_at' => optional($user->seller_removal_requested_at)?->toISOString(),
                ],
                'update_request' => [
                    'status' => $user->seller_update_request_status,
                    'requested_at' => optional($user->seller_update_requested_at)?->toISOString(),
                    'changed_fields' => array_keys($user->seller_update_request_diff ?? []),
                ],
            ],
        ]);
    }

    private function persistVerification(Request $request, \App\Models\User $user, bool $adminMode): RedirectResponse
    {
        if (! $adminMode && $user->seller_update_request_status === 'pending') {
            return redirect()->route('dashboard.seller')->with('success', 'seller-update-request-pending-lock');
        }

        $request->merge($this->normalizeOptionalUrlInputs($request));

        $validator = Validator::make($request->all(), [
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'company_city' => ['required', 'string', 'min:2', 'max:120'],
            'company_district' => ['nullable', 'string', 'max:120'],
            'company_neighborhood' => ['nullable', 'string', 'max:120'],
            'company_postal_code' => ['required', 'string', 'max:40'],
            'company_address_line' => ['required', 'string', 'min:8', 'max:255'],
            'service_category_ids' => ['required', 'array', 'min:1'],
            'service_category_ids.*' => ['required', 'integer', 'exists:categories,id', 'distinct'],
            'service_subcategory_ids' => ['nullable', 'array'],
            'service_subcategory_ids.*' => ['nullable', 'integer', 'exists:subcategories,id', 'distinct'],
            'service_subcategories_by_category' => ['nullable', 'array'],
            'service_subcategories_by_category.*' => ['nullable', 'array'],
            'service_subcategories_by_category.*.*' => ['nullable', 'integer', 'exists:subcategories,id', 'distinct'],
            'service_brand_ids' => ['nullable', 'array'],
            'service_brand_ids.*' => ['nullable', 'integer', 'exists:brands,id', 'distinct'],
            'service_country_codes' => ['required', 'array', 'min:1', 'max:10'],
            'service_country_codes.*' => ['required', 'string', 'size:2', 'distinct'],
            'service_ports_by_country' => ['required', 'array', 'min:1'],
            'service_ports_by_country.*' => ['required', 'array', 'min:1'],
            'service_ports_by_country.*.*' => ['required', 'integer', 'distinct', 'exists:ports,id'],
            'phone' => ['required', 'string', 'regex:/^\+\d{1,4}\s[0-9]{6,15}$/'],
            'landline_phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-()]{6,20}$/'],
            'contact_email' => ['required', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'regex:/^\+\d{1,4}\s[0-9]{6,15}$/'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'company_overview' => ['required', 'string', 'max:4000'],
            'port_coverage' => ['nullable', 'string', 'max:2000'],
            'registration_number' => ['required', 'string', 'min:3', 'max:255'],
            'keep_company_logo_path' => ['nullable', 'string'],
            'existing_company_registration_documents' => ['nullable', 'array'],
            'existing_company_registration_documents.*' => ['string'],
            'company_logo' => [$user->company_logo_path ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'company_registration_documents' => ['nullable', 'array'],
            'company_registration_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], $this->messages(), $this->attributes());

        $validator->after(function ($validator) use ($request, $user) {
            $selectedCountryCodes = collect($request->input('service_country_codes', []))
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => strtoupper(trim((string) $value)))
                ->values();
            $portsByCountry = collect($request->input('service_ports_by_country', []))
                ->mapWithKeys(fn ($portIds, $countryCode) => [
                    strtoupper(trim((string) $countryCode)) => collect($portIds)
                        ->filter(fn ($value) => filled($value))
                        ->map(fn ($value) => (int) $value)
                        ->values(),
                ]);
            $selectedPortIds = $portsByCountry->flatten()->values();
            $validCountryCodes = Port::query()
                ->active()
                ->whereIn('country_code', $selectedCountryCodes)
                ->distinct()
                ->pluck('country_code')
                ->map(fn ($value) => strtoupper((string) $value))
                ->values();

            if ($selectedCountryCodes->diff($validCountryCodes)->isNotEmpty()) {
                $validator->errors()->add('service_country_codes', 'Please select valid service countries.');
            }

            $subcategoriesByCategory = collect($request->input('service_subcategories_by_category', []))
                ->mapWithKeys(fn ($subcategoryIds, $categoryId) => [
                    (int) $categoryId => collect($subcategoryIds)
                        ->filter(fn ($value) => filled($value))
                        ->map(fn ($value) => (int) $value)
                        ->values(),
                ]);

            $selectedCategoryIds = collect($request->input('service_category_ids', []))
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => (int) $value)
                ->values();

            if ($subcategoriesByCategory->keys()->diff($selectedCategoryIds)->isNotEmpty()) {
                $validator->errors()->add('service_subcategory_ids', 'Subcategory selections must match the primary categories you selected.');
            }

            $missingSubcategoriesForCategory = $selectedCategoryIds
                ->filter(fn ($categoryId) => ! $subcategoriesByCategory->has($categoryId) || $subcategoriesByCategory[$categoryId]->isEmpty());

            if ($missingSubcategoriesForCategory->isNotEmpty()) {
                $validator->errors()->add('service_subcategory_ids', 'Please select at least 1 subcategory for every primary category you choose.');
            }

            $missingPortsForCountry = $selectedCountryCodes
                ->filter(fn ($countryCode) => ! $portsByCountry->has($countryCode) || $portsByCountry[$countryCode]->isEmpty());

            if ($missingPortsForCountry->isNotEmpty()) {
                $validator->errors()->add('service_ports_by_country', 'Please select at least one port for every selected country.');
            }

            if ($portsByCountry->keys()->diff($selectedCountryCodes)->isNotEmpty()) {
                $validator->errors()->add('service_ports_by_country', 'Port selections must match the countries you selected.');
            }

            $trimmedCompanyOverview = trim((string) $request->input('company_overview', ''));

            if (! filled($trimmedCompanyOverview)) {
                $validator->errors()->add('company_overview', 'Company overview is required.');
            }

            if ($selectedPortIds->isNotEmpty()) {
                $invalidPortSelection = Port::query()
                    ->active()
                    ->whereIn('id', $selectedPortIds)
                    ->whereNotIn('country_code', $selectedCountryCodes)
                    ->exists();

                if ($invalidPortSelection) {
                    $validator->errors()->add('service_ports_by_country', 'Selected ports must belong to the service countries you picked.');
                }
            }

            $keptRegistrationDocuments = collect($request->input('existing_company_registration_documents', []))
                ->filter(fn ($path) => filled($path));

            $newRegistrationDocuments = count($request->file('company_registration_documents', []));

            if ($keptRegistrationDocuments->isEmpty() && $newRegistrationDocuments === 0) {
                $validator->errors()->add('company_registration_documents', 'Please upload at least one company registration document.');
            }

            $hasLogo = filled($request->input('keep_company_logo_path'))
                || $request->hasFile('company_logo')
                || filled($user->company_logo_path);

            if (! $hasLogo) {
                $validator->errors()->add('company_logo', 'Company logo is required.');
            }

        });

        $validated = $validator->validate();

        $isUpdateRequest = ! $adminMode && $user->isApproved();

        if ($isUpdateRequest) {
            $currentUpdatePayload = is_array($user->seller_update_request_payload) ? $user->seller_update_request_payload : [];
            $basePath = "seller-verifications/{$user->id}/update-request";
            $sourceRegistrationDocuments = $currentUpdatePayload['company_registration_documents'] ?? $this->documentSet($user->company_registration_documents);

            $companyLogoPath = $this->storeUpdateRequestSingleFile(
                $request,
                'company_logo',
                $basePath.'/logo',
                $validated['keep_company_logo_path'] ?? null,
                $currentUpdatePayload['company_logo']['path'] ?? null,
            );

            $companyRegistrationDocuments = $this->syncUpdateRequestDocumentSet(
                $request,
                $basePath.'/company-registration',
                $sourceRegistrationDocuments,
                collect($validated['existing_company_registration_documents'] ?? []),
                'company_registration_documents',
            );

            $payload = $this->buildVerificationPayload(
                $validated,
                $companyLogoPath,
                $companyRegistrationDocuments,
            );

            $user->forceFill([
                'seller_update_request_status' => 'pending',
                'seller_update_request_payload' => $payload,
                'seller_update_request_diff' => $this->buildVerificationDiff($user, $payload),
                'seller_update_requested_at' => now(),
                'seller_update_rejection_reason' => null,
                'seller_update_rejection_note' => null,
                'seller_update_rejection_fields' => null,
                'seller_update_rejected_at' => null,
            ])->save();

            MarketplaceNotificationCenter::notifySellerUpdateRequestSubmitted($user);

            return redirect()->route('seller.dashboard')->with('success', 'seller-update-request-submitted');
        }

        $basePath = "seller-verifications/{$user->id}";
        $companyLogoPath = $this->storeSingleFile(
            $request,
            'company_logo',
            $basePath.'/logo',
            $validated['keep_company_logo_path'] ?? null,
            $user->company_logo_path,
        );
        $companyRegistrationDocuments = $this->syncDocumentSet(
            $request,
            $basePath.'/company-registration',
            $user->company_registration_documents ?? [],
            collect($validated['existing_company_registration_documents'] ?? []),
            'company_registration_documents',
        );
        $payload = $this->buildVerificationPayload(
            $validated,
            $companyLogoPath,
            $companyRegistrationDocuments,
        );

        $this->applyVerificationPayloadToUser($user, $payload, $adminMode);

        if (! $adminMode) {
            MarketplaceNotificationCenter::notifySellerVerificationSubmitted($user);

            return redirect()->route('approval.pending')->with('success', 'seller-verification-submitted');
        }

        return redirect()->route('admin.dashboard')->with('success', 'seller-verification-updated-admin');
    }

    private function normalizeOptionalUrlInputs(Request $request): array
    {
        $fields = [
            'website_url',
            'instagram_url',
            'linkedin_url',
            'facebook_url',
            'twitter_url',
            'telegram_url',
        ];

        $normalized = [];

        foreach ($fields as $field) {
            $normalized[$field] = $this->normalizeOptionalHttpUrl($request->input($field));
        }

        return $normalized;
    }

    private function normalizeOptionalHttpUrl(mixed $value): ?string
    {
        $input = trim((string) ($value ?? ''));

        if ($input === '') {
            return null;
        }

        $candidate = preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $input)
            ? $input
            : 'https://'.ltrim($input, '/');

        return filter_var($candidate, FILTER_VALIDATE_URL) ? $candidate : $input;
    }

    private function verificationDataFromUser(\App\Models\User $user): array
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

    private function buildVerificationPayload(
        array $validated,
        ?string $companyLogoPath,
        array $companyRegistrationDocuments,
    ): array {
        return [
            'company_name' => trim((string) $validated['company_name']),
            'phone' => trim((string) $validated['phone']),
            'landline_phone' => filled($validated['landline_phone'] ?? null) ? trim((string) $validated['landline_phone']) : null,
            'contact_email' => trim((string) $validated['contact_email']),
            'whatsapp_number' => filled($validated['whatsapp_number'] ?? null) ? trim((string) $validated['whatsapp_number']) : null,
            'instagram_url' => filled($validated['instagram_url'] ?? null) ? trim((string) $validated['instagram_url']) : null,
            'linkedin_url' => filled($validated['linkedin_url'] ?? null) ? trim((string) $validated['linkedin_url']) : null,
            'facebook_url' => filled($validated['facebook_url'] ?? null) ? trim((string) $validated['facebook_url']) : null,
            'twitter_url' => filled($validated['twitter_url'] ?? null) ? trim((string) $validated['twitter_url']) : null,
            'telegram_url' => filled($validated['telegram_url'] ?? null) ? trim((string) $validated['telegram_url']) : null,
            'country' => trim((string) $validated['country']),
            'company_address_line' => trim((string) $validated['company_address_line']),
            'company_city' => trim((string) $validated['company_city']),
            'company_district' => filled($validated['company_district'] ?? null) ? trim((string) $validated['company_district']) : null,
            'company_neighborhood' => filled($validated['company_neighborhood'] ?? null) ? trim((string) $validated['company_neighborhood']) : null,
            'company_postal_code' => trim((string) $validated['company_postal_code']),
            'company_overview' => filled($validated['company_overview'] ?? null) ? trim((string) $validated['company_overview']) : null,
            'port_coverage' => filled($validated['port_coverage'] ?? null) ? trim((string) $validated['port_coverage']) : null,
            'registration_number' => trim((string) $validated['registration_number']),
            'website_url' => filled($validated['website_url'] ?? null) ? trim((string) $validated['website_url']) : null,
            'service_category_ids' => array_values(array_map('intval', $validated['service_category_ids'])),
            'service_subcategory_ids' => array_values(array_map('intval', array_filter($validated['service_subcategory_ids'] ?? [], fn ($value) => filled($value)))),
            'service_subcategories_by_category' => collect($validated['service_subcategories_by_category'] ?? [])
                ->mapWithKeys(fn ($subcategoryIds, $categoryId) => [
                    (string) $categoryId => array_values(array_map('intval', array_filter($subcategoryIds ?? [], fn ($value) => filled($value)))),
                ])
                ->filter(fn ($subcategoryIds) => count($subcategoryIds) > 0)
                ->toArray(),
            'service_brand_ids' => array_values(array_map('intval', array_filter($validated['service_brand_ids'] ?? [], fn ($value) => filled($value)))),
            'service_country_codes' => array_values(array_map(fn ($value) => strtoupper((string) $value), $validated['service_country_codes'])),
            'service_ports_by_country' => collect($validated['service_ports_by_country'])
                ->mapWithKeys(fn ($portIds, $countryCode) => [
                    strtoupper((string) $countryCode) => array_values(array_map('intval', $portIds ?? [])),
                ])
                ->toArray(),
            'company_logo' => $this->singleFile($companyLogoPath),
            'company_registration_documents' => $this->documentSet($companyRegistrationDocuments),
        ];
    }

    private function applyVerificationPayloadToUser(\App\Models\User $user, array $payload, bool $adminMode): void
    {
        $address = collect([
            $payload['company_address_line'] ?? null,
            $payload['company_neighborhood'] ?? null,
            $payload['company_district'] ?? null,
            $payload['company_city'] ?? null,
            $payload['company_postal_code'] ?? null,
            $payload['country'] ?? null,
        ])->filter()->implode(', ');

        $approvalStatus = $adminMode ? ($user->approval_status === 'rejected' ? 'approved' : ($user->approval_status ?: 'approved')) : 'pending';
        $approvedAt = $adminMode ? ($user->approved_at ?: now()) : null;
        $submittedAt = $adminMode ? ($user->seller_verification_submitted_at ?: now()) : now();

        $logoPath = $payload['company_logo']['path'] ?? null;
        $companyRegistrationDocuments = $payload['company_registration_documents'] ?? [];

        $user->forceFill([
            'company_name' => $payload['company_name'] ?? null,
            'country' => $payload['country'] ?? null,
            'countries' => $payload['country'] ?? null,
            'phone' => $payload['phone'] ?? null,
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
            'company_logo_path' => $logoPath,
            'company_registration_documents' => $companyRegistrationDocuments,
            'tax_certificate_documents' => [],
            'service_authorization_documents' => [],
            'company_registration_document_path' => $companyRegistrationDocuments[0]['path'] ?? null,
            'tax_certificate_document_path' => null,
            'service_authorization_document_path' => null,
            'seller_verification_submitted_at' => $submittedAt,
            'approval_status' => $approvalStatus,
            'approved_at' => $approvedAt,
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
        ])->save();

        $user->servicePorts()->sync(
            collect($payload['service_ports_by_country'] ?? [])
                ->flatten()
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all()
        );

        if ($user->isApproved()) {
            app(SupplierServiceListingIndex::class)->syncSeller($user);
        } else {
            app(SupplierServiceListingIndex::class)->clearSeller($user);
        }
    }

    private function buildVerificationDiff(\App\Models\User $user, array $payload): array
    {
        $current = $this->verificationDataFromUser($user);
        $keys = [
            'company_name',
            'service_category_ids',
            'service_brand_ids',
            'service_country_codes',
            'service_ports_by_country',
            'country',
            'company_city',
            'company_district',
            'company_neighborhood',
            'company_postal_code',
            'company_address_line',
            'phone',
            'landline_phone',
            'contact_email',
            'website_url',
            'whatsapp_number',
            'telegram_url',
            'instagram_url',
            'linkedin_url',
            'facebook_url',
            'twitter_url',
            'company_overview',
            'port_coverage',
            'registration_number',
            'company_logo',
            'company_registration_documents',
        ];

        $diff = [];

        foreach ($keys as $key) {
            $from = $this->diffValueForField($key, $current);
            $to = $this->diffValueForField($key, $payload);

            if ($from !== $to) {
                $diff[$key] = [
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }

        return $diff;
    }

    private function diffValueForField(string $field, array $data): string
    {
        return match ($field) {
            'company_name', 'country', 'company_city', 'company_district', 'company_neighborhood', 'company_postal_code', 'company_address_line', 'phone', 'landline_phone', 'contact_email', 'website_url', 'whatsapp_number', 'telegram_url', 'instagram_url', 'linkedin_url', 'facebook_url', 'twitter_url', 'company_overview', 'port_coverage', 'registration_number'
                => trim((string) ($data[$field] ?? '')),
            'service_country_codes'
                => collect($data['service_country_codes'] ?? [])->filter()->implode(', '),
            'service_category_ids'
                => $this->formatCategoryDiff($data),
            'service_brand_ids'
                => $this->formatBrandDiff($data),
            'service_ports_by_country'
                => $this->formatPortDiff($data),
            'company_logo'
                => basename((string) ($data['company_logo']['path'] ?? '')),
            'company_registration_documents'
                => collect($data['company_registration_documents'] ?? [])->pluck('name')->filter()->implode(', '),
            default => '',
        };
    }

    private function formatCategoryDiff(array $data): string
    {
        $categoryIds = collect($data['service_category_ids'] ?? [])->map(fn ($value) => (int) $value)->filter()->values();
        $subcategoryIds = collect($data['service_subcategory_ids'] ?? [])->map(fn ($value) => (int) $value)->filter()->values();

        $categories = Category::query()->whereIn('id', $categoryIds)->orderBy('name')->pluck('name');
        $subcategories = Subcategory::query()->whereIn('id', $subcategoryIds)->orderBy('name')->pluck('name');

        return collect([$categories->implode(', '), $subcategories->implode(', ')])->filter()->implode(' | ');
    }

    private function formatBrandDiff(array $data): string
    {
        $brandIds = collect($data['service_brand_ids'] ?? [])->map(fn ($value) => (int) $value)->filter()->values();

        return Brand::query()
            ->whereIn('id', $brandIds)
            ->orderBy('name')
            ->pluck('name')
            ->implode(', ');
    }

    private function formatPortDiff(array $data): string
    {
        $portIds = collect($data['service_ports_by_country'] ?? [])
            ->flatten()
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        return Port::query()
            ->whereIn('id', $portIds)
            ->orderBy('country_code')
            ->orderBy('port_name')
            ->get(['country_code', 'port_name', 'unlocode'])
            ->map(fn (Port $port) => trim(collect([$port->country_code, $port->port_name, $port->unlocode])->filter()->implode(' - ')))
            ->implode(', ');
    }

    private function storeUpdateRequestSingleFile(
        Request $request,
        string $field,
        string $directory,
        ?string $keepPath,
        ?string $currentRequestPath
    ): ?string {
        if (! $request->hasFile($field)) {
            if ($currentRequestPath && $keepPath !== $currentRequestPath) {
                Storage::disk('public')->delete($currentRequestPath);
            }

            return $keepPath ?: null;
        }

        if ($currentRequestPath) {
            Storage::disk('public')->delete($currentRequestPath);
        }

        $file = $request->file($field);
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, 'public');
    }

    private function syncUpdateRequestDocumentSet(
        Request $request,
        string $directory,
        array $currentRequestDocuments,
        \Illuminate\Support\Collection $keptPaths,
        string $field
    ): array {
        $currentDocuments = collect($currentRequestDocuments)
            ->filter(fn ($document) => filled($document['path'] ?? null));

        $currentDocuments
            ->reject(fn ($document) => $keptPaths->contains($document['path']))
            ->filter(fn ($document) => str_starts_with((string) ($document['path'] ?? ''), $directory))
            ->each(fn ($document) => Storage::disk('public')->delete($document['path']));

        $keptDocuments = collect($currentRequestDocuments)
            ->filter(fn ($document) => $keptPaths->contains($document['path'] ?? null))
            ->values();

        $uploadedDocuments = collect($request->file($field, []))
            ->filter()
            ->map(function ($file) use ($directory) {
                $originalName = $file->getClientOriginalName();
                $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename, 'public');

                return [
                    'path' => $path,
                    'name' => $originalName,
                ];
            });

        return $keptDocuments->concat($uploadedDocuments)->values()->all();
    }

    public function requestRemoval(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isSeller(), 403);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:business_closed,duplicate_listing,wrong_account,not_needed,other'],
            'note' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $user->forceFill([
            'seller_removal_request_reason' => $validated['reason'],
            'seller_removal_request_note' => trim((string) $validated['note']),
            'seller_removal_request_status' => 'pending',
            'seller_removal_requested_at' => now(),
        ])->save();

        MarketplaceNotificationCenter::notifySellerRemovalRequest($user);

        return back()->with('success', 'seller-removal-request-submitted');
    }

    private function singleFile(?string $path): ?array
    {
        if (! $path) {
            return null;
        }

        return [
            'path' => $path,
            'name' => basename($path),
            'url' => '/storage/'.ltrim($path, '/'),
        ];
    }

    private function documentSet(?array $documents): array
    {
        return collect($documents ?? [])
            ->filter(fn ($document) => filled($document['path'] ?? null))
            ->map(fn ($document) => [
                'path' => $document['path'],
                'name' => $document['name'] ?? basename($document['path']),
                'url' => '/storage/'.ltrim($document['path'], '/'),
            ])
            ->values()
            ->all();
    }

    private function storeSingleFile(
        Request $request,
        string $field,
        string $directory,
        ?string $keepPath,
        ?string $existingPath
    ): ?string {
        if (! $request->hasFile($field)) {
            if ($keepPath && $keepPath === $existingPath) {
                return $existingPath;
            }

            if ($existingPath && ! $keepPath) {
                Storage::disk('public')->delete($existingPath);
            }

            return $keepPath ?: null;
        }

        if ($existingPath) {
            Storage::disk('public')->delete($existingPath);
        }

        $file = $request->file($field);
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, 'public');
    }

    private function syncDocumentSet(
        Request $request,
        string $directory,
        array $existingDocuments,
        \Illuminate\Support\Collection $keptPaths,
        string $field
    ): array {
        $currentDocuments = collect($existingDocuments)
            ->filter(fn ($document) => filled($document['path'] ?? null));

        $keptDocuments = $currentDocuments
            ->filter(fn ($document) => $keptPaths->contains($document['path']))
            ->values();

        $currentDocuments
            ->reject(fn ($document) => $keptPaths->contains($document['path']))
            ->each(fn ($document) => Storage::disk('public')->delete($document['path']));

        $uploadedDocuments = collect($request->file($field, []))
            ->filter()
            ->map(function ($file) use ($directory) {
                $originalName = $file->getClientOriginalName();
                $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename, 'public');

                return [
                    'path' => $path,
                    'name' => $originalName,
                ];
            });

        return $keptDocuments
            ->concat($uploadedDocuments)
            ->values()
            ->all();
    }

    private function messages(): array
    {
        return [
            'company_name.required' => 'Business name is required.',
            'country.required' => 'Country is required.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must include the country code.',
            'company_address_line.required' => 'Address line is required.',
            'company_city.required' => 'City is required.',
            'company_postal_code.required' => 'Postal code is required.',
            'company_overview.required' => 'Company overview is required.',
            'registration_number.required' => 'Company registration number is required.',
            'contact_email.required' => 'Company email is required.',
            'contact_email.email' => 'Please enter a valid email address.',
            'landline_phone.regex' => 'Please enter a valid landline number.',
            'whatsapp_number.regex' => 'WhatsApp number must include the country code.',
            'website_url.url' => 'Please enter a valid website URL.',
            'instagram_url.url' => 'Please enter a valid Instagram URL.',
            'linkedin_url.url' => 'Please enter a valid LinkedIn URL.',
            'facebook_url.url' => 'Please enter a valid Facebook URL.',
            'twitter_url.url' => 'Please enter a valid Twitter URL.',
            'telegram_url.url' => 'Please enter a valid Telegram URL.',
            'service_category_ids.required' => 'Business primary category is required.',
            'service_country_codes.required' => 'At least one service country is required.',
            'service_country_codes.max' => 'You can select up to 10 countries.',
            'service_ports_by_country.required' => 'At least one port is required.',
            'company_logo.required' => 'Company logo is required.',
            'company_logo.mimes' => 'Company logo must be a JPG, JPEG, PNG or WEBP file.',
            'company_registration_documents.*.mimes' => 'Company registration documents must be PDF, JPG, JPEG or PNG files.',
        ];
    }

    private function attributes(): array
    {
        return [
            'company_name' => 'business name',
            'country' => 'country',
            'phone' => 'phone number',
            'company_address_line' => 'address line',
            'company_city' => 'city',
            'company_district' => 'district',
            'company_neighborhood' => 'neighborhood',
            'company_postal_code' => 'postal code',
            'company_overview' => 'company overview',
            'registration_number' => 'company registration number',
            'website_url' => 'website',
            'landline_phone' => 'landline business phone',
            'contact_email' => 'company email',
            'whatsapp_number' => 'WhatsApp',
            'instagram_url' => 'Instagram',
            'linkedin_url' => 'LinkedIn',
            'facebook_url' => 'Facebook',
            'twitter_url' => 'Twitter',
            'telegram_url' => 'Telegram',
            'service_category_ids' => 'business primary category',
            'service_subcategory_ids' => 'business subcategory',
            'service_brand_ids' => 'brands',
            'service_country_codes' => 'service countries',
            'service_ports_by_country' => 'service ports',
            'company_logo' => 'company logo',
            'company_registration_documents' => 'company registration documents',
        ];
    }
}


