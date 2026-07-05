<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SellerVerificationAiReviewService
{
    public function __construct(
        private readonly SellerVerificationDocumentExtractor $documentExtractor
    ) {}

    public function isEnabled(): bool
    {
        return filled((string) config('services.openai.api_key'))
            && ! app()->environment('testing');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function review(User $user, array $payload): array
    {
        $profileChecks = $this->profileChecks($payload);
        $documents = $this->documentExtractor->extract($payload['company_registration_documents'] ?? []);

        if ($documents === []) {
            return $this->buildRejectedReview(
                $payload,
                $profileChecks,
                [],
                'documents_incomplete',
                ['company_registration_documents'],
                'No readable company registration document could be prepared for automatic verification.',
                [
                    'decision' => 'reject',
                    'confidence' => 'high',
                    'document_type' => 'unclear',
                    'quality' => 'unreadable',
                    'expiry_status' => 'unclear',
                    'company_name_match' => 'unclear',
                    'registration_number_match' => 'unclear',
                    'review_summary' => 'No readable company registration document could be prepared for automatic verification.',
                    'reasoning' => ['No readable company registration document was available for AI analysis.'],
                ]
            );
        }

        if (! $this->isEnabled()) {
            return $this->buildManualReview(
                $payload,
                $profileChecks,
                $documents,
                null,
                'Automatic AI document verification is not enabled right now, so this supplier verification remains pending for manual review.'
            );
        }

        $aiResult = $this->requestAiReview($payload, $documents);

        if (! is_array($aiResult)) {
            return $this->buildManualReview(
                $payload,
                $profileChecks,
                $documents,
                null,
                'Automatic AI document verification could not complete successfully, so this supplier verification remains pending for manual review.'
            );
        }

        $duplicateMatches = $this->findDuplicateDocuments($user, $documents);
        $documentType = $this->enumValue($aiResult['document_type'] ?? null, ['registration_document', 'unclear', 'not_registration_document'], 'unclear');
        $quality = $this->enumValue($aiResult['quality'] ?? null, ['clear', 'acceptable', 'low_quality', 'unreadable'], 'unclear');
        $expiryStatus = $this->enumValue($aiResult['expiry_status'] ?? null, ['not_shown', 'valid', 'expired', 'unclear'], 'unclear');
        $confidence = $this->enumValue($aiResult['confidence'] ?? null, ['high', 'medium', 'low'], 'low');
        $proposedDecision = $this->enumValue($aiResult['proposed_decision'] ?? null, ['approve', 'reject', 'manual_review'], 'manual_review');

        $extractedCompanyName = $this->cleanNullableString($aiResult['extracted_company_name'] ?? null);
        $extractedRegistrationNumber = $this->cleanNullableString($aiResult['extracted_registration_number'] ?? null);
        $companyNameMatch = $this->compareCompanyNames((string) ($payload['company_name'] ?? ''), $extractedCompanyName);
        $registrationNumberMatch = $this->compareRegistrationNumbers((string) ($payload['registration_number'] ?? ''), $extractedRegistrationNumber);

        $normalizedAi = [
            'decision' => $proposedDecision,
            'confidence' => $confidence,
            'document_type' => $documentType,
            'quality' => $quality,
            'expiry_status' => $expiryStatus,
            'issue_date' => $this->cleanNullableString($aiResult['issue_date'] ?? null),
            'expiry_date' => $this->cleanNullableString($aiResult['expiry_date'] ?? null),
            'extracted_company_name' => $extractedCompanyName,
            'extracted_registration_number' => $extractedRegistrationNumber,
            'company_name_match' => $companyNameMatch,
            'registration_number_match' => $registrationNumberMatch,
            'review_summary' => $this->cleanNullableString($aiResult['review_summary'] ?? null),
            'reasoning' => collect($aiResult['reasoning'] ?? [])
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->map(fn ($item) => trim($item))
                ->take(8)
                ->values()
                ->all(),
        ];

        if (! $profileChecks['passed']) {
            return $this->buildManualReview(
                $payload,
                $profileChecks,
                $documents,
                [
                    ...$normalizedAi,
                    'duplicate_status' => $duplicateMatches === [] ? 'clear' : 'duplicate_found',
                    'duplicate_matches' => $duplicateMatches,
                ],
                'Profile validation passed the request layer but still needs manual review because one or more required business fields could not be confirmed during the final verification gate.'
            );
        }

        if ($duplicateMatches !== []) {
            return $this->buildRejectedReview(
                $payload,
                $profileChecks,
                $documents,
                'compliance_issue',
                ['company_registration_documents'],
                'This company registration document already appears in another supplier application and requires manual compliance resolution before activation.',
                [
                    ...$normalizedAi,
                    'duplicate_status' => 'duplicate_found',
                    'duplicate_matches' => $duplicateMatches,
                ]
            );
        }

        if ($documentType === 'not_registration_document') {
            return $this->buildRejectedReview(
                $payload,
                $profileChecks,
                $documents,
                'documents_incomplete',
                ['company_registration_documents'],
                'The uploaded file does not appear to be a company registration document. Please upload a valid company registration document and submit again.',
                [
                    ...$normalizedAi,
                    'duplicate_status' => 'clear',
                    'duplicate_matches' => [],
                ]
            );
        }

        if (in_array($quality, ['low_quality', 'unreadable'], true)) {
            return $this->buildRejectedReview(
                $payload,
                $profileChecks,
                $documents,
                'documents_incomplete',
                ['company_registration_documents'],
                'The uploaded company registration document is too unclear to verify. Please upload a clearer document and submit again.',
                [
                    ...$normalizedAi,
                    'duplicate_status' => 'clear',
                    'duplicate_matches' => [],
                ]
            );
        }

        if ($expiryStatus === 'expired') {
            return $this->buildRejectedReview(
                $payload,
                $profileChecks,
                $documents,
                'compliance_issue',
                ['company_registration_documents'],
                'The uploaded company registration document appears to be expired. Please upload a current valid document and submit again.',
                [
                    ...$normalizedAi,
                    'duplicate_status' => 'clear',
                    'duplicate_matches' => [],
                ]
            );
        }

        if ($registrationNumberMatch === 'mismatch' || $companyNameMatch === 'mismatch') {
            $fields = ['company_registration_documents'];

            if ($companyNameMatch === 'mismatch') {
                $fields[] = 'company_name';
            }

            if ($registrationNumberMatch === 'mismatch') {
                $fields[] = 'registration_number';
            }

            return $this->buildRejectedReview(
                $payload,
                $profileChecks,
                $documents,
                'information_mismatch',
                $fields,
                'The company name or registration number extracted from the document does not match the submitted supplier information. Please correct the mismatch and submit again.',
                [
                    ...$normalizedAi,
                    'duplicate_status' => 'clear',
                    'duplicate_matches' => [],
                ]
            );
        }

        $canAutoApprove = $proposedDecision === 'approve'
            && $confidence === 'high'
            && $documentType === 'registration_document'
            && in_array($quality, ['clear', 'acceptable'], true)
            && $registrationNumberMatch === 'match'
            && $companyNameMatch === 'match'
            && $expiryStatus !== 'expired';

        if ($canAutoApprove) {
            return $this->buildApprovedReview(
                $payload,
                $profileChecks,
                $documents,
                [
                    ...$normalizedAi,
                    'duplicate_status' => 'clear',
                    'duplicate_matches' => [],
                ]
            );
        }

        return $this->buildManualReview(
            $payload,
            $profileChecks,
            $documents,
            [
                ...$normalizedAi,
                'duplicate_status' => 'clear',
                'duplicate_matches' => [],
            ],
            $normalizedAi['review_summary'] ?: 'The document passed basic checks but still needs manual review because the AI result was not confident enough for automatic approval or automatic rejection.'
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<string, mixed>|null
     */
    private function requestAiReview(array $payload, array $documents): ?array
    {
        $schema = $this->reviewSchema();
        $inputPayload = [
            'submitted_supplier' => [
                'company_name' => trim((string) ($payload['company_name'] ?? '')),
                'registration_number' => trim((string) ($payload['registration_number'] ?? '')),
                'country' => trim((string) ($payload['country'] ?? '')),
                'contact_email' => trim((string) ($payload['contact_email'] ?? '')),
            ],
            'documents' => collect($documents)
                ->map(fn ($document) => [
                    'name' => $document['name'] ?? null,
                    'mime_type' => $document['mime_type'] ?? null,
                    'source_type' => $document['source_type'] ?? null,
                    'ocr_lines' => collect($document['ocr_lines'] ?? [])->take(120)->values()->all(),
                ])
                ->values()
                ->all(),
        ];

        $imageInputs = collect($documents)
            ->flatMap(fn ($document) => collect($document['page_images'] ?? []))
            ->filter(fn ($image) => is_string($image) && trim($image) !== '')
            ->take(5)
            ->map(fn ($image) => [
                'type' => 'input_image',
                'image_url' => $image,
                'detail' => 'high',
            ])
            ->values()
            ->all();

        try {
            $requestPayload = [
                'model' => (string) config('services.openai.seller_verification_model', 'gpt-4o-mini'),
                'input' => $imageInputs === []
                    ? [
                        ['role' => 'system', 'content' => $this->reviewPrompt()],
                        ['role' => 'user', 'content' => json_encode($inputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ]
                    : [
                        ['role' => 'system', 'content' => $this->reviewPrompt()],
                        ['role' => 'user', 'content' => [
                            [
                                'type' => 'input_text',
                                'text' => json_encode($inputPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ],
                            ...$imageInputs,
                        ]],
                    ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => $schema['name'],
                        'strict' => true,
                        'schema' => $schema['schema'],
                    ],
                ],
            ];

            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->withToken((string) config('services.openai.api_key'))
                ->timeout((int) config('services.openai.timeout', 60))
                ->acceptJson()
                ->post('/responses', $requestPayload);

            if (! $response->successful()) {
                Log::warning('Seller verification AI review request failed.', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            $content = $response->json('output_text');

            if (! is_string($content) || trim($content) === '') {
                $content = Arr::get($response->json(), 'output.0.content.0.text');
            }

            if (! is_string($content) || trim($content) === '') {
                return null;
            }

            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $exception) {
            Log::warning('Seller verification AI review exception.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{passed: bool, missing_fields: array<int, string>}
     */
    private function profileChecks(array $payload): array
    {
        $checks = [
            'company_name' => filled($payload['company_name'] ?? null),
            'country' => filled($payload['country'] ?? null),
            'company_city' => filled($payload['company_city'] ?? null),
            'company_address_line' => filled($payload['company_address_line'] ?? null),
            'company_postal_code' => filled($payload['company_postal_code'] ?? null),
            'phone' => filled($payload['phone'] ?? null),
            'contact_email' => filled($payload['contact_email'] ?? null),
            'company_overview' => filled($payload['company_overview'] ?? null),
            'registration_number' => filled($payload['registration_number'] ?? null),
            'company_logo' => filled($payload['company_logo']['path'] ?? null),
            'service_category_ids' => ! empty($payload['service_category_ids'] ?? []),
            'service_subcategory_ids' => ! empty($payload['service_subcategory_ids'] ?? []),
            'service_country_codes' => ! empty($payload['service_country_codes'] ?? []),
            'service_ports_by_country' => ! empty(collect($payload['service_ports_by_country'] ?? [])->flatten()->filter()->all()),
        ];

        $missingFields = collect($checks)
            ->reject(fn ($passed) => $passed === true)
            ->keys()
            ->values()
            ->all();

        return [
            'passed' => $missingFields === [],
            'missing_fields' => $missingFields,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function findDuplicateDocuments(User $user, array $documents): array
    {
        $hashes = collect($documents)
            ->pluck('sha256')
            ->filter(fn ($hash) => is_string($hash) && trim($hash) !== '')
            ->values();

        if ($hashes->isEmpty()) {
            return [];
        }

        return User::query()
            ->where('role', 'seller')
            ->whereKeyNot($user->id)
            ->whereNotNull('company_registration_documents')
            ->get(['id', 'name', 'company_name', 'email', 'company_registration_documents'])
            ->flatMap(function (User $candidate) use ($hashes) {
                return collect($candidate->company_registration_documents ?? [])
                    ->map(function ($document) use ($candidate) {
                        $path = (string) ($document['path'] ?? '');

                        if ($path === '' || ! Storage::disk('public')->exists($path)) {
                            return null;
                        }

                        $hash = $document['sha256'] ?? $this->sha256ForPath($path);

                        if (! is_string($hash) || trim($hash) === '') {
                            return null;
                        }

                        return [
                            'user_id' => $candidate->id,
                            'company_name' => $candidate->company_name ?: $candidate->name,
                            'email' => $candidate->email,
                            'document_name' => $document['name'] ?? basename($path),
                            'sha256' => $hash,
                        ];
                    })
                    ->filter();
            })
            ->filter(fn ($match) => $hashes->contains($match['sha256'] ?? null))
            ->values()
            ->all();
    }

    private function sha256ForPath(string $path): ?string
    {
        try {
            $absolutePath = Storage::disk('public')->path($path);
            $hash = @hash_file('sha256', $absolutePath);

            return is_string($hash) && $hash !== '' ? $hash : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function compareRegistrationNumbers(?string $submitted, ?string $extracted): string
    {
        $submitted = $this->normalizeRegistrationNumber($submitted);
        $extracted = $this->normalizeRegistrationNumber($extracted);

        if ($submitted === '' || $extracted === '') {
            return 'unclear';
        }

        if ($submitted === $extracted) {
            return 'match';
        }

        if (strlen($submitted) >= 5 && str_contains($submitted, $extracted)) {
            return 'match';
        }

        if (strlen($extracted) >= 5 && str_contains($extracted, $submitted)) {
            return 'match';
        }

        similar_text($submitted, $extracted, $score);

        return $score >= 78 ? 'partial' : 'mismatch';
    }

    private function compareCompanyNames(?string $submitted, ?string $extracted): string
    {
        $submittedPrimary = $this->normalizeCompanyName($submitted, false);
        $extractedPrimary = $this->normalizeCompanyName($extracted, false);

        if ($submittedPrimary === '' || $extractedPrimary === '') {
            return 'unclear';
        }

        if ($submittedPrimary === $extractedPrimary) {
            return 'match';
        }

        $submittedStripped = $this->normalizeCompanyName($submitted, true);
        $extractedStripped = $this->normalizeCompanyName($extracted, true);

        if ($submittedStripped !== '' && $submittedStripped === $extractedStripped) {
            return 'match';
        }

        similar_text($submittedPrimary, $extractedPrimary, $primaryScore);
        similar_text($submittedStripped, $extractedStripped, $strippedScore);

        $score = max($primaryScore, $strippedScore);

        return $score >= 78 ? 'partial' : 'mismatch';
    }

    private function normalizeRegistrationNumber(?string $value): string
    {
        $value = Str::upper(Str::ascii((string) $value));

        return preg_replace('/[^A-Z0-9]+/', '', $value) ?: '';
    }

    private function normalizeCompanyName(?string $value, bool $stripLegalSuffixes): string
    {
        $value = Str::upper(Str::ascii((string) $value));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?: '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');

        if (! $stripLegalSuffixes || $value === '') {
            return $value;
        }

        $tokens = collect(explode(' ', $value))
            ->reject(fn ($token) => in_array($token, [
                'CO', 'COMPANY', 'LTD', 'LIMITED', 'LLC', 'INC', 'CORP', 'CORPORATION',
                'PTY', 'PTE', 'BV', 'NV', 'SA', 'SRL', 'PLC', 'THE',
            ], true))
            ->values();

        return trim($tokens->implode(' '));
    }

    private function cleanNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function enumValue(mixed $value, array $allowed, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{passed: bool, missing_fields: array<int, string>}  $profileChecks
     * @param  array<int, array<string, mixed>>  $documents
     * @param  array<string, mixed>  $normalizedAi
     * @return array<string, mixed>
     */
    private function buildApprovedReview(array $payload, array $profileChecks, array $documents, array $normalizedAi): array
    {
        return [
            'decision' => 'approve',
            'rejection_reason' => null,
            'rejection_fields' => [],
            'rejection_note' => null,
            'summary' => 'The company registration document was verified with high confidence and the supplier profile passed the automatic verification gate.',
            'review' => $this->reviewPayload($payload, $profileChecks, $documents, $normalizedAi, 'approve'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{passed: bool, missing_fields: array<int, string>}  $profileChecks
     * @param  array<int, array<string, mixed>>  $documents
     * @param  array<string, mixed>|null  $normalizedAi
     * @return array<string, mixed>
     */
    private function buildManualReview(array $payload, array $profileChecks, array $documents, ?array $normalizedAi, string $summary): array
    {
        return [
            'decision' => 'manual_review',
            'rejection_reason' => null,
            'rejection_fields' => [],
            'rejection_note' => null,
            'summary' => $summary,
            'review' => $this->reviewPayload(
                $payload,
                $profileChecks,
                $documents,
                [
                    'decision' => 'manual_review',
                    'confidence' => $normalizedAi['confidence'] ?? 'low',
                    'document_type' => $normalizedAi['document_type'] ?? 'unclear',
                    'quality' => $normalizedAi['quality'] ?? 'unclear',
                    'expiry_status' => $normalizedAi['expiry_status'] ?? 'unclear',
                    'issue_date' => $normalizedAi['issue_date'] ?? null,
                    'expiry_date' => $normalizedAi['expiry_date'] ?? null,
                    'extracted_company_name' => $normalizedAi['extracted_company_name'] ?? null,
                    'extracted_registration_number' => $normalizedAi['extracted_registration_number'] ?? null,
                    'company_name_match' => $normalizedAi['company_name_match'] ?? 'unclear',
                    'registration_number_match' => $normalizedAi['registration_number_match'] ?? 'unclear',
                    'review_summary' => $normalizedAi['review_summary'] ?? $summary,
                    'reasoning' => $normalizedAi['reasoning'] ?? [],
                    'duplicate_status' => $normalizedAi['duplicate_status'] ?? 'clear',
                    'duplicate_matches' => $normalizedAi['duplicate_matches'] ?? [],
                ],
                'manual_review'
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{passed: bool, missing_fields: array<int, string>}  $profileChecks
     * @param  array<int, array<string, mixed>>  $documents
     * @param  array<string, mixed>  $normalizedAi
     * @return array<string, mixed>
     */
    private function buildRejectedReview(
        array $payload,
        array $profileChecks,
        array $documents,
        string $reason,
        array $fields,
        string $note,
        array $normalizedAi
    ): array {
        return [
            'decision' => 'reject',
            'rejection_reason' => $reason,
            'rejection_fields' => array_values(array_unique($fields)),
            'rejection_note' => $note,
            'summary' => $note,
            'review' => $this->reviewPayload(
                $payload,
                $profileChecks,
                $documents,
                [
                    ...$normalizedAi,
                    'decision' => 'reject',
                    'review_summary' => $normalizedAi['review_summary'] ?? $note,
                ],
                'reject'
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{passed: bool, missing_fields: array<int, string>}  $profileChecks
     * @param  array<int, array<string, mixed>>  $documents
     * @param  array<string, mixed>  $normalizedAi
     * @return array<string, mixed>
     */
    private function reviewPayload(array $payload, array $profileChecks, array $documents, array $normalizedAi, string $decision): array
    {
        return [
            'decision' => $decision,
            'reviewed_at' => now()->toISOString(),
            'model' => (string) config('services.openai.seller_verification_model', 'gpt-4o-mini'),
            'submitted' => [
                'company_name' => trim((string) ($payload['company_name'] ?? '')),
                'registration_number' => trim((string) ($payload['registration_number'] ?? '')),
                'country' => trim((string) ($payload['country'] ?? '')),
                'contact_email' => trim((string) ($payload['contact_email'] ?? '')),
            ],
            'profile_checks' => $profileChecks,
            'documents' => collect($documents)
                ->map(fn ($document) => [
                    'name' => $document['name'] ?? null,
                    'path' => $document['path'] ?? null,
                    'mime_type' => $document['mime_type'] ?? null,
                    'size' => $document['size'] ?? null,
                    'sha256' => $document['sha256'] ?? null,
                    'source_type' => $document['source_type'] ?? null,
                ])
                ->values()
                ->all(),
            'analysis' => [
                'confidence' => $normalizedAi['confidence'] ?? 'low',
                'document_type' => $normalizedAi['document_type'] ?? 'unclear',
                'quality' => $normalizedAi['quality'] ?? 'unclear',
                'expiry_status' => $normalizedAi['expiry_status'] ?? 'unclear',
                'issue_date' => $normalizedAi['issue_date'] ?? null,
                'expiry_date' => $normalizedAi['expiry_date'] ?? null,
                'extracted_company_name' => $normalizedAi['extracted_company_name'] ?? null,
                'extracted_registration_number' => $normalizedAi['extracted_registration_number'] ?? null,
                'company_name_match' => $normalizedAi['company_name_match'] ?? 'unclear',
                'registration_number_match' => $normalizedAi['registration_number_match'] ?? 'unclear',
                'duplicate_status' => $normalizedAi['duplicate_status'] ?? 'clear',
                'duplicate_matches' => $normalizedAi['duplicate_matches'] ?? [],
                'review_summary' => $normalizedAi['review_summary'] ?? null,
                'reasoning' => $normalizedAi['reasoning'] ?? [],
            ],
        ];
    }

    /**
     * @return array{name: string, schema: array<string, mixed>}
     */
    private function reviewSchema(): array
    {
        return [
            'name' => 'seller_verification_document_review',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [
                    'proposed_decision',
                    'confidence',
                    'document_type',
                    'quality',
                    'expiry_status',
                    'extracted_company_name',
                    'extracted_registration_number',
                    'issue_date',
                    'expiry_date',
                    'review_summary',
                    'reasoning',
                ],
                'properties' => [
                    'proposed_decision' => [
                        'type' => 'string',
                        'enum' => ['approve', 'reject', 'manual_review'],
                    ],
                    'confidence' => [
                        'type' => 'string',
                        'enum' => ['high', 'medium', 'low'],
                    ],
                    'document_type' => [
                        'type' => 'string',
                        'enum' => ['registration_document', 'unclear', 'not_registration_document'],
                    ],
                    'quality' => [
                        'type' => 'string',
                        'enum' => ['clear', 'acceptable', 'low_quality', 'unreadable'],
                    ],
                    'expiry_status' => [
                        'type' => 'string',
                        'enum' => ['not_shown', 'valid', 'expired', 'unclear'],
                    ],
                    'extracted_company_name' => [
                        'type' => ['string', 'null'],
                    ],
                    'extracted_registration_number' => [
                        'type' => ['string', 'null'],
                    ],
                    'issue_date' => [
                        'type' => ['string', 'null'],
                    ],
                    'expiry_date' => [
                        'type' => ['string', 'null'],
                    ],
                    'review_summary' => [
                        'type' => 'string',
                    ],
                    'reasoning' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function reviewPrompt(): string
    {
        return <<<'PROMPT'
You are reviewing supplier company registration documents for a maritime supplier platform.

Your job is to read the submitted document content and decide whether the document appears to be a real company registration document for the submitted supplier profile.

Important rules:
- Focus on the company registration document only.
- Extract the company name if visible.
- Extract the company registration number if visible.
- Extract issue date and expiry date only if clearly visible.
- If there is no expiry date, use expiry_status = not_shown.
- Determine whether the file looks like a company registration document, an unclear document, or clearly a different document type.
- Determine whether the document quality is clear, acceptable, low_quality, or unreadable.
- Do not invent missing values.
- Use manual_review when evidence is mixed or unclear.
- Use approve only when the document strongly supports the submitted supplier profile.
- Use reject only when there is a clear document problem or a clear mismatch.

Return JSON only.
PROMPT;
    }
}
