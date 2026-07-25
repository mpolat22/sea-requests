<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendOnboardingCompletionEmail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OutreachContact;
use App\Models\Port;
use App\Models\Subcategory;
use App\Models\User;
use App\Support\AdminDashboardData;
use App\Support\AuthCountryCatalog;
use App\Support\Onboarding\OnboardingCompletionMailer;
use App\Support\Onboarding\OnboardingDraftCreator;
use App\Support\UserFacingMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminOnboardingController extends Controller
{
    public function index(Request $request, AdminDashboardData $dashboardData): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $audience = (string) $request->string('audience', 'seller');
        $status = (string) $request->string('status', 'all');
        $search = trim((string) $request->string('search', ''));

        $query = OutreachContact::query()
            ->whereIn('audience', ['seller', 'buyer'])
            ->latest('id');

        if (in_array($audience, ['seller', 'buyer'], true)) {
            $query->where('audience', $audience);
        }

        if ($status !== 'all') {
            $query->where('source_payload->onboarding_status', $status);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('organization_name', 'like', "%{$search}%")
                    ->orWhere('source_name', 'like', "%{$search}%");
            });
        }

        $records = $query
            ->paginate(10)
            ->withQueryString()
            ->through(fn (OutreachContact $contact) => $this->recordPayload($contact));

        $sellerVerificationOptions = $this->sellerVerificationOptions();

        return Inertia::render('Admin/Onboarding/Index', [
            'dashboard' => $dashboardData->dashboard(),
            'activeTab' => 'onboarding',
            'records' => $records,
            'summary' => [
                'draft' => $this->statusCount('draft'),
                'ready' => $this->statusCount('ready'),
                'account_created' => $this->statusCount('account_created'),
                'email_queued' => $this->statusCount('email_queued'),
                'email_sent' => $this->statusCount('email_sent'),
                'seller_total' => OutreachContact::query()->where('audience', 'seller')->whereNotNull('source_payload->onboarding_status')->count(),
                'buyer_total' => OutreachContact::query()->where('audience', 'buyer')->whereNotNull('source_payload->onboarding_status')->count(),
            ],
            'sellerVerificationOptions' => $sellerVerificationOptions,
            'filters' => [
                'audience' => $audience,
                'status' => $status,
                'search' => $search,
            ],
            'urls' => [
                'index' => route('admin.onboarding'),
                'manual_store' => route('admin.onboarding.manual.store'),
                'bulk_import_store' => route('admin.onboarding.bulk-import.store'),
            ],
        ]);
    }

    public function storeManualProfile(Request $request, OnboardingDraftCreator $drafts, UserFacingMail $mail): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $request->merge($this->normalizeManualUrlInputs($request));

        $validated = $request->validate([
            'audience' => ['required', Rule::in(['seller', 'buyer'])],
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'contact_email' => ['required_without:email', 'nullable', 'email:rfc', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'landline_phone' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'string', 'max:255'],
            'telegram_url' => ['nullable', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'string', 'max:255'],
            'twitter_url' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'company_city' => ['nullable', 'string', 'max:120'],
            'company_district' => ['nullable', 'string', 'max:120'],
            'company_neighborhood' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'company_postal_code' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'company_address_line' => ['nullable', 'string', 'max:255'],
            'company_overview' => ['nullable', 'string', 'max:4000'],
            'port_coverage' => ['nullable', 'string', 'max:2000'],
            'business_activity' => ['nullable', 'string', 'max:2000'],
            'serviced_ports' => ['nullable', 'string', 'max:4000'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'service_category_ids' => ['nullable', 'array'],
            'service_category_ids.*' => ['nullable', 'integer', 'exists:categories,id', 'distinct'],
            'service_subcategory_ids' => ['nullable', 'array'],
            'service_subcategory_ids.*' => ['nullable', 'integer', 'exists:subcategories,id', 'distinct'],
            'service_subcategories_by_category' => ['nullable', 'array'],
            'service_subcategories_by_category.*' => ['nullable', 'array'],
            'service_subcategories_by_category.*.*' => ['nullable', 'integer', 'exists:subcategories,id', 'distinct'],
            'service_brand_ids' => ['nullable', 'array'],
            'service_brand_ids.*' => ['nullable', 'integer', 'exists:brands,id', 'distinct'],
            'service_country_codes' => ['nullable', 'array', 'max:10'],
            'service_country_codes.*' => ['nullable', 'string', 'size:2', 'distinct'],
            'service_ports_by_country' => ['nullable', 'array'],
            'service_ports_by_country.*' => ['nullable', 'array'],
            'service_ports_by_country.*.*' => ['nullable', 'integer', 'distinct', 'exists:ports,id'],
            'company_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'company_registration_documents' => ['nullable', 'array'],
            'company_registration_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'company_name.required' => 'Company name is required before creating an onboarding record.',
            'contact_email.required_without' => 'Company email is required before creating an onboarding record.',
            'contact_email.email' => 'Please enter a valid company email address.',
        ]);

        $email = strtolower(trim((string) ($validated['contact_email'] ?? $validated['email'] ?? '')));

        if (User::query()->where('email', $email)->exists()) {
            return back()->withErrors([
                'contact_email' => 'This email already has a platform account. Use the existing user record instead.',
            ]);
        }

        $basePath = 'onboarding-profiles/'.Str::slug($validated['company_name']).'-'.Str::random(8);
        $companyLogoPath = $this->storeOnboardingSingleFile($request, 'company_logo', $basePath.'/logo');
        $companyRegistrationDocuments = $this->storeOnboardingDocumentSet($request, 'company_registration_documents', $basePath.'/company-registration');
        $servicePortsByCountry = collect($validated['service_ports_by_country'] ?? [])
            ->mapWithKeys(fn ($portIds, $countryCode) => [
                strtoupper((string) $countryCode) => collect($portIds ?? [])->filter()->map(fn ($value) => (int) $value)->unique()->values()->all(),
            ])
            ->filter(fn ($portIds) => count($portIds) > 0)
            ->toArray();
        $serviceCountryCodes = collect($validated['service_country_codes'] ?? [])
            ->map(fn ($value) => strtoupper((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $fallbackServicedPorts = $this->manualServicedPorts((string) ($validated['serviced_ports'] ?? ''));
        $resolvedServicedPorts = $servicePortsByCountry ? $this->matchedServicedPorts($servicePortsByCountry) : $fallbackServicedPorts;

        $parsed = [
            'company_name' => trim($validated['company_name']),
            'email' => $email,
            'contact_name' => $this->manualTrim($validated['contact_name'] ?? null),
            'phone' => $this->manualTrim($validated['phone'] ?? null),
            'landline_phone' => $this->manualTrim($validated['landline_phone'] ?? null),
            'whatsapp_number' => $this->manualTrim($validated['whatsapp_number'] ?? null),
            'website_url' => $this->manualTrim($validated['website_url'] ?? null),
            'telegram_url' => $this->manualTrim($validated['telegram_url'] ?? null),
            'instagram_url' => $this->manualTrim($validated['instagram_url'] ?? null),
            'linkedin_url' => $this->manualTrim($validated['linkedin_url'] ?? null),
            'facebook_url' => $this->manualTrim($validated['facebook_url'] ?? null),
            'twitter_url' => $this->manualTrim($validated['twitter_url'] ?? null),
            'country' => $this->manualTrim($validated['country'] ?? null),
            'city' => $this->manualTrim($validated['company_city'] ?? $validated['city'] ?? null),
            'company_city' => $this->manualTrim($validated['company_city'] ?? $validated['city'] ?? null),
            'company_district' => $this->manualTrim($validated['company_district'] ?? null),
            'company_neighborhood' => $this->manualTrim($validated['company_neighborhood'] ?? null),
            'postal_code' => $this->manualTrim($validated['company_postal_code'] ?? $validated['postal_code'] ?? null),
            'company_postal_code' => $this->manualTrim($validated['company_postal_code'] ?? $validated['postal_code'] ?? null),
            'address' => $this->manualTrim($validated['company_address_line'] ?? $validated['address'] ?? null),
            'company_address_line' => $this->manualTrim($validated['company_address_line'] ?? $validated['address'] ?? null),
            'company_overview' => $this->manualTrim($validated['company_overview'] ?? null),
            'port_coverage' => $this->manualTrim($validated['port_coverage'] ?? null),
            'registration_number' => $this->manualTrim($validated['registration_number'] ?? null),
            'business_activity' => $this->manualTrim($validated['business_activity'] ?? $validated['port_coverage'] ?? null),
            'service_category_ids' => array_values(array_map('intval', array_filter($validated['service_category_ids'] ?? [], fn ($value) => filled($value)))),
            'service_subcategory_ids' => array_values(array_map('intval', array_filter($validated['service_subcategory_ids'] ?? [], fn ($value) => filled($value)))),
            'service_subcategories_by_category' => collect($validated['service_subcategories_by_category'] ?? [])
                ->mapWithKeys(fn ($subcategoryIds, $categoryId) => [
                    (string) $categoryId => collect($subcategoryIds ?? [])->filter()->map(fn ($value) => (int) $value)->unique()->values()->all(),
                ])
                ->filter(fn ($subcategoryIds) => count($subcategoryIds) > 0)
                ->toArray(),
            'service_brand_ids' => array_values(array_map('intval', array_filter($validated['service_brand_ids'] ?? [], fn ($value) => filled($value)))),
            'service_country_codes' => $serviceCountryCodes,
            'service_ports_by_country' => $servicePortsByCountry,
            'company_logo' => $companyLogoPath ? $this->onboardingSingleFilePayload($companyLogoPath) : null,
            'company_registration_documents' => $companyRegistrationDocuments,
            'serviced_ports' => $resolvedServicedPorts,
        ];
        $rawProfile = $this->manualRawProfile($parsed, $parsed['serviced_ports']);

        $result = $drafts->create(
            audience: $validated['audience'],
            parsed: $parsed,
            rawProfile: $rawProfile,
            sourceName: 'Manual onboarding profile',
            createdFrom: 'manual_profile',
            adminId: $request->user()->id,
        );

        if (! $result['contact']) {
            return back()->with('error', 'Manual onboarding profile could not be saved. Please try again.');
        }

        $payload = $result['contact']->source_payload ?? [];
        $result['contact']->forceFill([
            'source_payload' => array_merge($payload, [
                'onboarding_status' => data_get($payload, 'onboarding_status') === 'account_created' ? 'account_created' : 'ready',
            ]),
        ])->save();

        $contact = $result['contact']->refresh();
        $this->createAccount($request, $contact);
        $contact->refresh();

        $emailResult = $this->sendCompletionEmailForContact($contact, $mail);

        if (! $emailResult['ok']) {
            return back()->with('error', $emailResult['message']);
        }

        return back()->with('success', $result['result'] === 'duplicate'
            ? 'Manual onboarding profile updated, account linked, and completion email sent.'
            : 'Manual onboarding profile saved, account created, and completion email sent.');
    }

    public function storeBulkImport(Request $request, OnboardingDraftCreator $drafts): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'audience' => ['required', Rule::in(['seller', 'buyer'])],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:20480'],
        ], [
            'file.required' => 'Please upload an Excel or CSV file with Company Name and Email columns.',
            'file.mimes' => 'Only CSV, TXT, XLS, and XLSX files are supported for onboarding import.',
        ]);

        $rows = $this->parseCompanyEmailImport($request->file('file'));

        if ($rows === []) {
            return back()->withErrors([
                'file' => 'No valid company rows were found. The first row must contain Company Name and Email.',
            ]);
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'existing_users' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'emails_queued' => 0,
        ];
        $seen = [];
        $fileName = $request->file('file')->getClientOriginalName();
        $queuePosition = 0;

        foreach ($rows as $row) {
            $email = strtolower(trim((string) $row['email']));
            $companyName = trim((string) $row['company_name']);

            if ($companyName === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['invalid']++;
                continue;
            }

            if (isset($seen[$email])) {
                $stats['duplicates']++;
                continue;
            }

            $seen[$email] = true;

            if (User::query()->where('email', $email)->exists()) {
                $stats['existing_users']++;
                continue;
            }

            $parsed = [
                'company_name' => $companyName,
                'email' => $email,
            ];

            $result = $drafts->create(
                audience: $validated['audience'],
                parsed: $parsed,
                rawProfile: $this->companyEmailImportRawProfile($companyName, $email),
                sourceName: 'Bulk company import',
                createdFrom: 'bulk_company_import',
                adminId: $request->user()->id,
            );

            if (! $result['contact']) {
                $stats['invalid']++;
                continue;
            }

            $contact = $result['contact']->refresh();
            $payload = $contact->source_payload ?? [];

            $contact->forceFill([
                'source_payload' => array_merge($payload, [
                    'onboarding_status' => 'ready',
                    'bulk_import_file_name' => $fileName,
                    'bulk_imported_at' => now()->toIso8601String(),
                    'bulk_import_expires_at' => now()->addDays(14)->toIso8601String(),
                ]),
            ])->save();

            $this->createAccount($request, $contact->refresh());
            $contact = $contact->refresh();
            $scheduledFor = now()->addMinutes($queuePosition * 2);
            $queuePosition++;

            $payload = $contact->source_payload ?? [];
            $contact->forceFill([
                'last_result' => 'completion_email_queued',
                'source_payload' => array_merge($payload, [
                    'onboarding_status' => 'email_queued',
                    'completion_email_queued_at' => now()->toIso8601String(),
                    'completion_email_scheduled_for' => $scheduledFor->toIso8601String(),
                    'completion_email_queue_interval_minutes' => 2,
                ]),
            ])->save();

            SendOnboardingCompletionEmail::dispatch($contact->id)->delay($scheduledFor);

            if ($result['result'] === 'duplicate') {
                $stats['updated']++;
            } else {
                $stats['created']++;
            }

            $stats['emails_queued']++;
        }

        $accountsTouched = $stats['created'] + $stats['updated'];
        $skippedRows = $stats['existing_users'] + $stats['duplicates'] + $stats['invalid'];

        if ($accountsTouched === 0 && $skippedRows === 0) {
            return back()->withErrors([
                'file' => 'No accounts were created. Please check duplicates, existing users, and email format.',
            ])->with('error', 'No onboarding accounts were created from this file.');
        }

        if ($accountsTouched === 0) {
            return back()->with('success', sprintf(
                'Import completed. No new accounts were created because all valid rows were already in the system. Existing accounts skipped: %d. Duplicate rows skipped: %d. Invalid rows skipped: %d.',
                $stats['existing_users'],
                $stats['duplicates'],
                $stats['invalid'],
            ));
        }

        return back()->with('success', sprintf(
            'Import completed. New accounts created: %d. Existing onboarding records updated: %d. Completion emails queued: %d. Emails will be sent one by one every 2 minutes. Existing platform accounts skipped: %d. Duplicate rows skipped: %d. Invalid rows skipped: %d.',
            $stats['created'],
            $stats['updated'],
            $stats['emails_queued'],
            $stats['existing_users'],
            $stats['duplicates'],
            $stats['invalid'],
        ));
    }
    public function update(Request $request, OutreachContact $contact): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'audience' => ['required', Rule::in(['seller', 'buyer'])],
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('outreach_contacts', 'email')->ignore($contact->id),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'business_activity' => ['nullable', 'string', 'max:2000'],
            'company_overview' => ['nullable', 'string', 'max:2000'],
        ]);

        $email = strtolower(trim($validated['email']));

        if (User::query()->where('email', $email)->where('id', '!=', data_get($contact->source_payload, 'user_id'))->exists()) {
            return back()->withErrors([
                'email' => 'This email already belongs to another platform user.',
            ]);
        }
        $payload = $contact->source_payload ?? [];
        $parsed = $payload['parsed'] ?? [];

        $contact->fill([
            'email' => $email,
            'audience' => $validated['audience'],
            'organization_name' => trim($validated['company_name']),
            'source_payload' => array_merge($payload, [
                'onboarding_status' => $payload['onboarding_status'] ?? 'ready',
                'parsed' => array_merge($parsed, [
                    'company_name' => trim($validated['company_name']),
                    'email' => $email,
                    'contact_name' => trim((string) ($validated['contact_name'] ?? '')),
                    'phone' => trim((string) ($validated['phone'] ?? '')),
                    'website_url' => trim((string) ($validated['website_url'] ?? '')),
                    'country' => trim((string) ($validated['country'] ?? '')),
                    'city' => trim((string) ($validated['city'] ?? '')),
                    'postal_code' => trim((string) ($validated['postal_code'] ?? '')),
                    'address' => trim((string) ($validated['address'] ?? '')),
                    'business_activity' => trim((string) ($validated['business_activity'] ?? '')),
                    'company_overview' => trim((string) ($validated['company_overview'] ?? '')),
                ]),
            ]),
        ])->save();

        return back()->with('success', 'Onboarding profile updated.');
    }

    public function createAccount(Request $request, OutreachContact $contact): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $payload = $contact->source_payload ?? [];
        $parsed = $payload['parsed'] ?? [];
        $audience = in_array($contact->audience, ['seller', 'buyer'], true) ? $contact->audience : 'seller';
        $email = strtolower((string) ($parsed['email'] ?? $contact->email));

        if (blank($email) || blank($parsed['company_name'] ?? null)) {
            return back()->withErrors([
                'record' => 'Company name and email are required before creating the account.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $companyName = trim((string) $parsed['company_name']);
            $address = collect([
                $parsed['company_address_line'] ?? $parsed['address'] ?? null,
                $parsed['company_neighborhood'] ?? null,
                $parsed['company_district'] ?? null,
                $parsed['company_city'] ?? $parsed['city'] ?? null,
                $parsed['company_postal_code'] ?? $parsed['postal_code'] ?? null,
                $parsed['country'] ?? null,
            ])->filter()->implode(', ');
            $companyRegistrationDocuments = $this->onboardingDocumentPayload($parsed['company_registration_documents'] ?? []);
            $companyLogoPath = data_get($parsed, 'company_logo.path');

            $user = User::query()->create([
                'name' => filled($parsed['contact_name'] ?? null) ? trim((string) $parsed['contact_name']) : $companyName,
                'email' => $email,
                'locale' => 'en',
                'password' => Str::random(40),
                'email_verified_at' => now(),
                'role' => $audience,
                'company_name' => $companyName,
                'phone' => filled($parsed['phone'] ?? null) ? trim((string) $parsed['phone']) : null,
                'landline_phone' => filled($parsed['landline_phone'] ?? null) ? trim((string) $parsed['landline_phone']) : null,
                'whatsapp_number' => filled($parsed['whatsapp_number'] ?? null) ? trim((string) $parsed['whatsapp_number']) : null,
                'country' => filled($parsed['country'] ?? null) ? trim((string) $parsed['country']) : null,
                'countries' => filled($parsed['country'] ?? null) ? trim((string) $parsed['country']) : null,
                'company_city' => filled($parsed['company_city'] ?? $parsed['city'] ?? null) ? trim((string) ($parsed['company_city'] ?? $parsed['city'])) : null,
                'company_district' => filled($parsed['company_district'] ?? null) ? trim((string) $parsed['company_district']) : null,
                'company_neighborhood' => filled($parsed['company_neighborhood'] ?? null) ? trim((string) $parsed['company_neighborhood']) : null,
                'company_postal_code' => filled($parsed['company_postal_code'] ?? $parsed['postal_code'] ?? null) ? trim((string) ($parsed['company_postal_code'] ?? $parsed['postal_code'])) : null,
                'company_address_line' => filled($parsed['company_address_line'] ?? $parsed['address'] ?? null) ? trim((string) ($parsed['company_address_line'] ?? $parsed['address'])) : null,
                'company_address' => $address ?: null,
                'website_url' => filled($parsed['website_url'] ?? null) ? trim((string) $parsed['website_url']) : null,
                'telegram_url' => filled($parsed['telegram_url'] ?? null) ? trim((string) $parsed['telegram_url']) : null,
                'instagram_url' => filled($parsed['instagram_url'] ?? null) ? trim((string) $parsed['instagram_url']) : null,
                'linkedin_url' => filled($parsed['linkedin_url'] ?? null) ? trim((string) $parsed['linkedin_url']) : null,
                'facebook_url' => filled($parsed['facebook_url'] ?? null) ? trim((string) $parsed['facebook_url']) : null,
                'twitter_url' => filled($parsed['twitter_url'] ?? null) ? trim((string) $parsed['twitter_url']) : null,
                'contact_email' => filled($parsed['contact_email'] ?? null) ? trim((string) $parsed['contact_email']) : $email,
                'company_description' => filled($parsed['company_overview'] ?? null) ? trim((string) $parsed['company_overview']) : null,
                'company_overview' => filled($parsed['company_overview'] ?? null) ? trim((string) $parsed['company_overview']) : null,
                'port_coverage' => filled($parsed['port_coverage'] ?? null) ? trim((string) $parsed['port_coverage']) : null,
                'registration_number' => filled($parsed['registration_number'] ?? null) ? trim((string) $parsed['registration_number']) : null,
                'service_category_ids' => array_values(array_map('intval', $parsed['service_category_ids'] ?? [])),
                'service_subcategory_ids' => array_values(array_map('intval', $parsed['service_subcategory_ids'] ?? [])),
                'service_subcategories_by_category' => $parsed['service_subcategories_by_category'] ?? [],
                'service_brand_ids' => array_values(array_map('intval', $parsed['service_brand_ids'] ?? [])),
                'service_country_codes' => $parsed['service_country_codes'] ?? [],
                'company_logo_path' => filled($companyLogoPath) ? $companyLogoPath : null,
                'company_registration_documents' => $companyRegistrationDocuments,
                'company_registration_document_path' => $companyRegistrationDocuments[0]['path'] ?? null,
                'tax_certificate_documents' => [],
                'service_authorization_documents' => [],
                'approval_status' => $audience === 'seller' ? 'pending' : 'approved',
                'approved_at' => $audience === 'buyer' ? now() : null,
            ]);

            if ($audience === 'seller') {
                $user->servicePorts()->sync(
                    collect($parsed['service_ports_by_country'] ?? [])
                        ->flatten()
                        ->map(fn ($value) => (int) $value)
                        ->unique()
                        ->values()
                        ->all()
                );
            }
        }

        if (! $user->email_verified_at) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        $contact->forceFill([
            'status' => OutreachContact::STATUS_REGISTERED,
            'source_payload' => array_merge($payload, [
                'onboarding_status' => 'account_created',
                'user_id' => $user->id,
                'account_created_at' => $payload['account_created_at'] ?? now()->toIso8601String(),
            ]),
        ])->save();

        return back()->with('success', 'Platform account created and linked to this onboarding record.');
    }

    public function sendCompletionEmail(Request $request, OutreachContact $contact, UserFacingMail $mail): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $result = $this->sendCompletionEmailForContact($contact, $mail);

        if (! $result['ok']) {
            return back()->withErrors([
                'record' => $result['message'],
            ]);
        }

        return back()->with('success', 'Account completion email sent.');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function sendCompletionEmailForContact(OutreachContact $contact, UserFacingMail $mail): array
    {
        return app(OnboardingCompletionMailer::class)->send($contact, $mail);
    }

    public function destroy(Request $request, OutreachContact $contact): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $contact->delete();

        return back()->with('success', 'Onboarding record deleted.');
    }

    private function statusCount(string $status): int
    {
        return OutreachContact::query()
            ->whereIn('audience', ['seller', 'buyer'])
            ->where('source_payload->onboarding_status', $status)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function sellerVerificationOptions(): array
    {
        $serviceCountries = AuthCountryCatalog::serviceCountries();

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

        return [
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
            'dialCodeOptions' => AuthCountryCatalog::dialCodeOptions(),
            'portsByCountry' => $portsByCountry,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function onboardingSingleFilePayload(?string $path): ?array
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

    /**
     * @param array<int, mixed> $documents
     * @return array<int, array<string, mixed>>
     */
    private function onboardingDocumentPayload(array $documents): array
    {
        return collect($documents)
            ->filter(fn ($document) => filled($document['path'] ?? null))
            ->map(function ($document) {
                $path = $document['path'];

                return array_filter([
                    'path' => $path,
                    'name' => $document['name'] ?? basename($path),
                    'mime_type' => $document['mime_type'] ?? null,
                    'size' => isset($document['size']) ? (int) $document['size'] : null,
                    'sha256' => $document['sha256'] ?? null,
                    'url' => '/storage/'.ltrim($path, '/'),
                ], fn ($value, $key) => ! is_null($value) || in_array($key, ['path', 'name', 'url'], true), ARRAY_FILTER_USE_BOTH);
            })
            ->values()
            ->all();
    }

    private function storeOnboardingSingleFile(Request $request, string $field, string $directory): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, 'public');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storeOnboardingDocumentSet(Request $request, string $field, string $directory): array
    {
        return collect($request->file($field, []))
            ->filter()
            ->map(function ($file) use ($directory) {
                $realPath = $file->getRealPath();
                $sha256 = is_string($realPath) && $realPath !== '' ? @hash_file('sha256', $realPath) : null;
                $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename, 'public');

                return [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => is_numeric($file->getSize()) ? (int) $file->getSize() : null,
                    'sha256' => is_string($sha256) && $sha256 !== '' ? $sha256 : null,
                    'url' => '/storage/'.ltrim($path, '/'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, array<int, int>> $servicePortsByCountry
     * @return array<int, array<string, mixed>>
     */
    private function matchedServicedPorts(array $servicePortsByCountry): array
    {
        $portIds = collect($servicePortsByCountry)
            ->flatten()
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();

        if ($portIds->isEmpty()) {
            return [];
        }

        return Port::query()
            ->whereIn('id', $portIds)
            ->orderBy('country_name')
            ->orderBy('port_name')
            ->get(['id', 'country_name', 'port_name', 'unlocode'])
            ->map(fn (Port $port) => [
                'port' => $port->port_name,
                'country' => $port->country_name,
                'unlocode' => $port->unlocode,
                'matched' => true,
                'port_id' => $port->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, ?string>
     */
    private function normalizeManualUrlInputs(Request $request): array
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
            $normalized[$field] = $this->normalizeManualOptionalHttpUrl($request->input($field));
        }

        return $normalized;
    }

    private function normalizeManualOptionalHttpUrl(mixed $value): ?string
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

    private function manualTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<int, array{port:string,country:?string,matched:bool}>
     */
    private function manualServicedPorts(string $value): array
    {
        return collect(preg_split('/\R+|,|;/', $value) ?: [])
            ->map(fn (string $line) => trim(preg_replace('/\s+/', ' ', $line) ?: ''))
            ->filter()
            ->unique(fn (string $line) => mb_strtolower($line))
            ->take(100)
            ->map(function (string $line): array {
                $country = null;
                $port = $line;

                if (str_contains($line, '/')) {
                    [$port, $country] = array_map('trim', explode('/', $line, 2));
                }

                return [
                    'port' => mb_convert_case($port, MB_CASE_TITLE, 'UTF-8'),
                    'country' => $country ? mb_convert_case($country, MB_CASE_TITLE, 'UTF-8') : null,
                    'matched' => false,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<int, array<string, mixed>> $servicedPorts
     */
    private function manualRawProfile(array $validated, array $servicedPorts): string
    {
        $lines = [
            trim((string) $validated['company_name']),
            'CONTACT INFO',
            'Phone: '.trim((string) ($validated['phone'] ?? '')),
            'Email: '.strtolower(trim((string) $validated['email'])),
            'Website: '.trim((string) ($validated['website_url'] ?? '')),
            'Address: '.trim((string) ($validated['address'] ?? '')),
            'City: '.trim((string) ($validated['city'] ?? '')),
            'Postal Code: '.trim((string) ($validated['postal_code'] ?? '')),
            'Country: '.trim((string) ($validated['country'] ?? '')),
            'BUSINESS ACTIVITY',
            trim((string) ($validated['business_activity'] ?? '')),
            'SERVICED PORTS',
            collect($servicedPorts)->map(fn (array $port) => trim(($port['port'] ?? '').' '.($port['country'] ?? '')))->implode(' flag '),
            'COMPANY DESCRIPTION',
            trim((string) ($validated['company_overview'] ?? '')),
        ];

        return collect($lines)->filter(fn (string $line) => trim($line) !== '')->implode(PHP_EOL);
    }
    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */

    /**
     * @return array<int, array{company_name:string,email:string}>
     */
    private function parseCompanyEmailImport($file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();

        $headerRow = null;
        $headerMap = [];

        foreach ($rows as $index => $row) {
            $normalized = collect($row)
                ->mapWithKeys(fn ($value, $column) => [$column => $this->normalizeImportHeader((string) $value)])
                ->filter()
                ->all();

            if (in_array('companyname', $normalized, true) && in_array('email', $normalized, true)) {
                $headerRow = $index;
                $headerMap = array_flip($normalized);
                break;
            }
        }

        if ($headerRow === null || ! isset($headerMap['companyname'], $headerMap['email'])) {
            throw ValidationException::withMessages([
                'file' => 'The first row must contain exactly these columns: Company Name and Email.',
            ]);
        }

        $records = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerRow) {
                continue;
            }

            $companyName = trim((string) ($row[$headerMap['companyname']] ?? ''));
            $email = strtolower(trim((string) ($row[$headerMap['email']] ?? '')));

            if ($companyName === '' && $email === '') {
                continue;
            }

            $records[] = [
                'company_name' => $companyName,
                'email' => $email,
            ];
        }

        return $records;
    }

    private function normalizeImportHeader(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value))) ?: '';
    }

    private function companyEmailImportRawProfile(string $companyName, string $email): string
    {
        return "Company Name: {$companyName}".PHP_EOL."Email: {$email}";
    }
    private function recordPayload(OutreachContact $contact): array
    {
        $payload = $contact->source_payload ?? [];
        $parsed = $payload['parsed'] ?? [];
        $user = isset($payload['user_id']) ? User::query()->find($payload['user_id']) : null;

        return [
            'id' => $contact->id,
            'audience' => $contact->audience,
            'email' => $contact->email,
            'company_name' => $contact->organization_name,
            'status' => $payload['onboarding_status'] ?? 'draft',
            'source_name' => $contact->source_name,
            'sent_count' => $contact->sent_count,
            'last_sent_at' => optional($contact->last_sent_at)->toIso8601String(),
            'created_at' => optional($contact->created_at)->toIso8601String(),
            'parsed' => $parsed,
            'user' => $user ? [
                'id' => $user->id,
                'role' => $user->role,
                'approval_status' => $user->approval_status,
                'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
                'seller_verification_submitted_at' => optional($user->seller_verification_submitted_at)->toIso8601String(),
            ] : null,
            'urls' => [
                'update' => route('admin.onboarding.records.update', $contact),
                'create_account' => route('admin.onboarding.records.create-account', $contact),
                'send_completion_email' => route('admin.onboarding.records.send-completion-email', $contact),
                'delete' => route('admin.onboarding.records.destroy', $contact),
            ],
        ];
    }
}

