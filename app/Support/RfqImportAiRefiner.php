<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class RfqImportAiRefiner
{
    private const GENERAL_FIELDS = [
        'reference_no',
        'company_name',
        'ship_name',
        'imo_number',
        'status',
        'country',
        'port',
        'requisition_date',
        'due_date',
        'currency',
        'priority',
        'general_notes',
    ];

    private const ITEM_FIELDS = [
        'product_name',
        'part_no',
        'manufacturer',
        'model_type',
        'catalog_code',
        'serial_number',
        'drawing_number',
        'quantity',
        'unit',
        'rob',
        'quality',
        'comments',
    ];

    private const ITEM_FIELD_LABELS = [
        'product_name' => 'Product',
        'part_no' => 'Part No',
        'manufacturer' => 'Manufacturer',
        'model_type' => 'MFG Model / Type',
        'catalog_code' => 'Catalog Code',
        'serial_number' => 'Serial Number',
        'drawing_number' => 'Drawing Number',
        'quantity' => 'Qty',
        'unit' => 'Unit',
        'rob' => 'ROB',
        'quality' => 'Quality',
        'comments' => 'Comments',
    ];

    public function isEnabled(): bool
    {
        return filled((string) config('services.openai.api_key'));
    }

    public function refinePreview(array $preview): array
    {
        if (! $this->isEnabled() || empty($preview['items'])) {
            return $preview;
        }

        $response = $this->requestStructuredJson(
            $this->refinementSchema(),
            $this->refinementSystemPrompt(),
            $this->buildRefinementPayload($preview)
        );

        if (! is_array($response) || empty($response['items']) || ! is_array($response['items'])) {
            return $preview;
        }

        $items = collect($response['items'])
            ->map(fn ($item) => $this->normalizeItem($item))
            ->map(fn ($item, $index) => $this->mergeWithOriginalItem(
                $item,
                $preview['items'][$index] ?? null
            ))
            ->filter(fn ($item) => $this->isMeaningfulItem($item))
            ->values()
            ->all();

        $items = $this->heuristicMergeAdjacentItems($items);

        if ($items === []) {
            return $preview;
        }

        $refinedPreview = $preview;
        $refinedPreview['items'] = $items;
        $refinedPreview['summary']['items_count'] = count($items);
        $refinedPreview['summary']['ai_refined'] = true;
        $refinedPreview['summary']['review_count'] = max(0, (int) ($preview['summary']['review_count'] ?? 0) - 1);

        if (! $this->looksHealthy($refinedPreview)) {
            $consolidated = $this->consolidateBrokenItems($refinedPreview);

            if ($consolidated !== null && $this->looksHealthierThan($consolidated, $refinedPreview)) {
                $refinedPreview = $consolidated;
            }
        }

        if (! $this->looksHealthy($refinedPreview) || $this->shouldForceRecovery($refinedPreview)) {
            $recovered = $this->recoverFromRows(
                $preview['raw']['ocr_rows'] ?? [],
                $preview['summary']['sheet_name'] ?? 'Imported Document',
                $preview['summary']['file_name'] ?? 'Imported Document',
                $preview['summary']['source_type'] ?? 'document',
                $preview['raw']['ocr_lines'] ?? []
            );

            if ($recovered !== null && $this->looksHealthierThan($recovered, $refinedPreview)) {
                $recovered['general'] = array_merge($recovered['general'] ?? [], $preview['general'] ?? []);
                $recovered['summary']['ai_refined'] = true;
                $recovered['summary']['ai_recovered_after_refine'] = true;

                return $recovered;
            }
        }

        return $refinedPreview;
    }

    public function extractBestPreviewFromRows(
        array $rows,
        string $sheetName = 'Imported Document',
        string $fileName = 'Imported Document',
        string $sourceType = 'document',
        array $ocrLines = [],
        array $customAliases = [],
        array $currentPreview = []
    ): ?array {
        if (! $this->isEnabled() || $rows === []) {
            return null;
        }

        $response = $this->requestStructuredJson(
            $this->recoverySchema(),
            $this->aiFirstExtractionPrompt(),
            [
                'source' => [
                    'file_name' => $fileName,
                    'sheet_name' => $sheetName,
                    'source_type' => $sourceType,
                ],
                'template_aliases' => $customAliases,
                'current_preview' => [
                    'general' => $currentPreview['general'] ?? [],
                    'items' => $currentPreview['items'] ?? [],
                ],
                'ocr_lines' => collect($ocrLines)->map(fn ($line) => $this->normalizeText($line))->filter()->take(200)->values()->all(),
                'rows' => collect($rows)
                    ->map(fn ($row) => collect($row)->map(fn ($value) => $this->normalizeText($value))->all())
                    ->take(200)
                    ->values()
                    ->all(),
            ]
        );

        if (! is_array($response) || empty($response['items']) || ! is_array($response['items'])) {
            return null;
        }

        $preview = $this->buildPreviewFromAiResponse($response, $sheetName, $fileName, $sourceType, true);

        return $preview['items'] === [] ? null : $preview;
    }

    private function consolidateBrokenItems(array $preview): ?array
    {
        $response = $this->requestStructuredJson(
            $this->refinementSchema(),
            $this->consolidationSystemPrompt(),
            [
                'source' => [
                    'file_name' => $preview['summary']['file_name'] ?? null,
                    'sheet_name' => $preview['summary']['sheet_name'] ?? null,
                    'source_type' => $preview['summary']['source_type'] ?? 'document',
                ],
                'current_items' => $preview['items'] ?? [],
                'ocr_lines' => collect($preview['raw']['ocr_lines'] ?? [])->take(160)->values()->all(),
                'raw_item_rows' => collect($preview['raw']['item_rows'] ?? [])->take(50)->values()->all(),
            ]
        );

        if (! is_array($response) || empty($response['items']) || ! is_array($response['items'])) {
            return null;
        }

        $items = collect($response['items'])
            ->map(fn ($item) => $this->normalizeItem($item))
            ->filter(fn ($item) => $this->isMeaningfulItem($item))
            ->values()
            ->all();

        $items = $this->heuristicMergeAdjacentItems($items);

        if ($items === []) {
            return null;
        }

        $candidate = $preview;
        $candidate['items'] = $items;
        $candidate['summary']['items_count'] = count($items);
        $candidate['summary']['ai_refined'] = true;
        $candidate['summary']['ai_consolidated'] = true;

        return $candidate;
    }

    public function recoverFromRows(
        array $rows,
        string $sheetName = 'Imported Document',
        string $fileName = 'Imported Document',
        string $sourceType = 'document',
        array $ocrLines = [],
        array $customAliases = []
    ): ?array {
        if (! $this->isEnabled() || $rows === []) {
            return null;
        }

        $response = $this->requestStructuredJson(
            $this->recoverySchema(),
            $this->recoverySystemPrompt(),
            $this->buildRecoveryPayload($rows, $sheetName, $fileName, $sourceType, $ocrLines, $customAliases)
        );

        if (! is_array($response) || empty($response['items']) || ! is_array($response['items'])) {
            return null;
        }

        $preview = $this->buildPreviewFromAiResponse($response, $sheetName, $fileName, $sourceType, false);

        return $preview['items'] === [] ? null : $preview;
    }

    public function extractFromImageFile(
        UploadedFile $file,
        string $sheetName = 'Imported Image',
        array $customAliases = [],
        array $ocrLines = [],
        array $ocrRows = []
    ): ?array {
        if (! $this->isEnabled()) {
            return null;
        }

        $mimeType = $file->getMimeType() ?: 'image/png';
        $bytes = @file_get_contents($file->getRealPath());

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $dataUrl = 'data:'.$mimeType.';base64,'.base64_encode($bytes);

        $response = $this->requestStructuredJsonWithImage(
            $this->recoverySchema(),
            $this->imageVisionPrompt(),
            [
                'source' => [
                    'file_name' => $file->getClientOriginalName(),
                    'sheet_name' => $sheetName,
                    'source_type' => 'image',
                ],
                'template_aliases' => $customAliases,
                'ocr_rows' => collect($ocrRows)
                    ->map(fn ($row) => collect($row)->map(fn ($value) => $this->normalizeText($value))->all())
                    ->take(200)
                    ->values()
                    ->all(),
                'ocr_lines' => collect($ocrLines)
                    ->map(fn ($line) => $this->normalizeText($line))
                    ->filter()
                    ->take(160)
                    ->values()
                    ->all(),
            ],
            $dataUrl
        );

        if (! is_array($response) || empty($response['items']) || ! is_array($response['items'])) {
            return null;
        }

        $preview = $this->buildPreviewFromAiResponse(
            $response,
            $sheetName,
            $file->getClientOriginalName(),
            'image',
            true
        );

        return $preview['items'] === [] ? null : $preview;
    }

    public function extractFromDocumentImages(
        array $imageDataUrls,
        string $fileName,
        string $sheetName = 'Imported PDF',
        string $sourceType = 'pdf',
        array $customAliases = [],
        array $ocrLines = [],
        array $ocrRows = []
    ): ?array {
        if (! $this->isEnabled()) {
            return null;
        }

        $imageDataUrls = collect($imageDataUrls)
            ->filter(fn ($image) => is_string($image) && str_starts_with($image, 'data:image/'))
            ->take(3)
            ->values()
            ->all();

        if ($imageDataUrls === []) {
            return null;
        }

        $response = $this->requestStructuredJsonWithImages(
            $this->recoverySchema(),
            $this->pdfVisionPrompt(),
            [
                'source' => [
                    'file_name' => $fileName,
                    'sheet_name' => $sheetName,
                    'source_type' => $sourceType,
                ],
                'template_aliases' => $customAliases,
                'ocr_rows' => collect($ocrRows)
                    ->map(fn ($row) => collect($row)->map(fn ($value) => $this->normalizeText($value))->all())
                    ->take(200)
                    ->values()
                    ->all(),
                'ocr_lines' => collect($ocrLines)
                    ->map(fn ($line) => $this->normalizeText($line))
                    ->filter()
                    ->take(160)
                    ->values()
                    ->all(),
            ],
            $imageDataUrls
        );

        if (! is_array($response) || empty($response['items']) || ! is_array($response['items'])) {
            return null;
        }

        $preview = $this->buildPreviewFromAiResponse(
            $response,
            $sheetName,
            $fileName,
            $sourceType,
            true
        );

        return $preview['items'] === [] ? null : $preview;
    }

    private function requestStructuredJson(array $schema, string $systemPrompt, array $payload): ?array
    {
        try {
            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->withToken((string) config('services.openai.api_key'))
                ->timeout((int) config('services.openai.timeout', 60))
                ->acceptJson()
                ->post('/responses', [
                    'model' => (string) config('services.openai.rfq_import_model', 'gpt-4o-mini'),
                    'input' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => $schema['name'],
                            'strict' => true,
                            'schema' => $schema['schema'],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('RFQ import AI refinement request failed.', [
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
            Log::warning('RFQ import AI refinement exception.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function requestStructuredJsonWithImage(array $schema, string $systemPrompt, array $payload, string $imageDataUrl): ?array
    {
        return $this->requestStructuredJsonWithImages($schema, $systemPrompt, $payload, [$imageDataUrl]);
    }

    private function requestStructuredJsonWithImages(array $schema, string $systemPrompt, array $payload, array $imageDataUrls): ?array
    {
        try {
            $imageInputs = collect($imageDataUrls)
                ->filter(fn ($imageDataUrl) => is_string($imageDataUrl) && trim($imageDataUrl) !== '')
                ->map(fn ($imageDataUrl) => [
                    'type' => 'input_image',
                    'image_url' => $imageDataUrl,
                    'detail' => 'high',
                ])
                ->values()
                ->all();

            if ($imageInputs === []) {
                return null;
            }

            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->withToken((string) config('services.openai.api_key'))
                ->timeout((int) config('services.openai.timeout', 60))
                ->acceptJson()
                ->post('/responses', [
                    'model' => (string) config('services.openai.rfq_import_model', 'gpt-4o-mini'),
                    'input' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => [
                            [
                                'type' => 'input_text',
                                'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
                ]);

            if (! $response->successful()) {
                Log::warning('RFQ image vision extraction request failed.', [
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
            Log::warning('RFQ image vision extraction exception.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function buildRefinementPayload(array $preview): array
    {
        return [
            'source' => [
                'file_name' => $preview['summary']['file_name'] ?? null,
                'sheet_name' => $preview['summary']['sheet_name'] ?? null,
                'source_type' => $preview['summary']['source_type'] ?? 'document',
            ],
            'general' => $preview['general'] ?? [],
            'mapped_columns' => $preview['summary']['mapped_columns'] ?? [],
            'current_items' => $preview['items'] ?? [],
            'raw_item_columns' => $preview['raw']['item_columns'] ?? [],
            'raw_item_rows' => collect($preview['raw']['item_rows'] ?? [])
                ->take(40)
                ->values()
                ->all(),
            'ocr_rows' => collect($preview['raw']['ocr_rows'] ?? [])
                ->take(120)
                ->values()
                ->all(),
            'ocr_lines' => collect($preview['raw']['ocr_lines'] ?? [])
                ->take(120)
                ->values()
                ->all(),
        ];
    }

    private function buildRecoveryPayload(
        array $rows,
        string $sheetName,
        string $fileName,
        string $sourceType,
        array $ocrLines = [],
        array $customAliases = []
    ): array
    {
        return [
            'source' => [
                'file_name' => $fileName,
                'sheet_name' => $sheetName,
                'source_type' => $sourceType,
            ],
            'template_aliases' => $customAliases,
            'ocr_lines' => collect($ocrLines)
                ->map(fn ($line) => $this->normalizeText($line))
                ->filter()
                ->take(160)
                ->values()
                ->all(),
            'rows' => collect($rows)
                ->map(fn ($row) => collect($row)
                    ->map(fn ($value) => $this->normalizeText($value))
                    ->all())
                ->take(120)
                ->values()
                ->all(),
        ];
    }

    private function buildPreviewFromAiResponse(array $response, string $sheetName, string $fileName, string $sourceType, bool $aiFirst = false): array
    {
        $general = collect(Arr::only($response['general'] ?? [], self::GENERAL_FIELDS))
            ->map(fn ($value) => $this->normalizeText($value))
            ->filter(fn ($value) => $value !== '')
            ->all();

        $items = collect($response['items'] ?? [])
            ->map(fn ($item) => $this->normalizeItem($item))
            ->filter(fn ($item) => $this->isMeaningfulItem($item))
            ->values()
            ->all();

        $items = $this->heuristicMergeAdjacentItems($items);

        $mappedColumns = collect(self::ITEM_FIELDS)
            ->filter(fn ($field) => collect($items)->contains(fn ($item) => ($item[$field] ?? '') !== ''))
            ->values()
            ->all();

        return [
            'summary' => [
                'file_name' => $fileName,
                'sheet_name' => $sheetName,
                'source_type' => $sourceType,
                'items_count' => count($items),
                'mapped_columns' => $mappedColumns,
                'review_count' => count($general) + max(1, count($mappedColumns)),
                'ai_refined' => true,
                'ai_recovered' => ! $aiFirst,
                'ai_first_extracted' => $aiFirst,
            ],
            'general' => $general,
            'mapping' => [
                'general' => [],
                'items' => collect($mappedColumns)
                    ->map(fn ($field) => [
                        'source' => $aiFirst ? 'AI extracted' : 'AI recovered',
                        'target' => self::ITEM_FIELD_LABELS[$field] ?? $field,
                        'field' => $field,
                        'score' => 82,
                        'level' => 'medium',
                    ])
                    ->all(),
            ],
            'confidence' => [
                'general' => collect($general)->mapWithKeys(fn ($value, $field) => [
                    $field => [
                        'source' => $aiFirst ? 'AI extracted' : 'AI recovered',
                        'score' => 80,
                        'method' => $aiFirst ? 'ai_first' : 'ai_recovered',
                        'level' => 'medium',
                    ],
                ])->all(),
                'items' => collect($mappedColumns)
                    ->map(fn ($field) => [
                        'source' => $aiFirst ? 'AI extracted' : 'AI recovered',
                        'field' => $field,
                        'score' => 82,
                        'level' => 'medium',
                    ])
                    ->all(),
            ],
            'items' => $items,
            'raw' => [
                'general_pairs' => [],
                'item_columns' => [],
                'item_rows' => [],
            ],
        ];
    }

    private function refinementSystemPrompt(): string
    {
        return <<<'PROMPT'
You refine RFQ imported line items for a maritime procurement form.

Your job:
- Use the current parsed items, raw row values, and detected columns to produce a cleaner final item list.
- Use OCR line text as the source of truth when a product name or row looks split.
- Merge OCR-broken continuation fragments back into the correct item.
- Remove fake rows, header rows, and rows that are only dashes, single fragments, or obvious OCR noise.
- Split combined values into the correct fields, especially Qty, Unit, ROB, and Comments.
- Keep item order.
- Do not invent values.
- If a value is missing or uncertain, return an empty string.
- If raw OCR rows contain a fuller product text than the current parsed item, prefer the fuller product text.
- If Qty, Unit, or ROB are already clearly present in the parsed data or raw rows, do not drop them.
- When a row contains values like "3 PCS 0 Urgent departure", split them into Qty=3, Unit=PCS, ROB=0, Comments="Urgent departure".

Important rules:
- Product names must stay whole. Do not split one real product into multiple items.
- Quantity must contain only the numeric quantity as a string, like "2" or "12".
- Unit must contain only the unit text, like "PCS", "SET", "LOT", "KIT".
- ROB must contain only the numeric ROB value as a string.
- Comments must contain only note/comment text, not qty/unit/rob.
- Keep Manufacturer, Model, Catalog Code, Serial Number, and Drawing Number in their own fields whenever possible.
- Ignore false OCR fragments such as isolated words, broken suffixes, repeated header terms, or placeholder dashes.
PROMPT;
    }

    private function recoverySystemPrompt(): string
    {
        return <<<'PROMPT'
You are recovering an RFQ requisition from OCR rows extracted from a PDF or image.

Return:
- general fields when they are clearly visible
- a clean final item list

Important rules:
- Use only values supported by the OCR rows. Do not invent data.
- Product names may span multiple OCR fragments. Merge them into a single real item.
- OCR lines may contain the real row text more clearly than the tabular rows. Use them when rows look split or incomplete.
- Detect likely item table headers and map them into the RFQ fields.
- Qty must be numeric string only.
- Unit must be a short unit string such as PCS, SET, LOT, KIT, EA, KG, LTR.
- ROB must be numeric string only.
- Empty, fake, or noise rows must be dropped.
- If a field is not present, return an empty string.
- General Notes should only contain actual high-level notes, not copied item-row fragments unless they clearly belong there.
PROMPT;
    }

    private function aiFirstExtractionPrompt(): string
    {
        return <<<'PROMPT'
You are the primary extraction engine for RFQ imports.

Your goal is to convert uploaded spreadsheet, PDF, or OCR rows into this exact procurement schema:
- general fields
- item list

Use these principles:
- Treat the uploaded rows as the main source of truth.
- Use template_aliases when provided. They are user-specific hints about what their own column names usually mean, and they should be treated as strong mapping guidance.
- Use current_preview only as a fallback hint, not as truth.
- Prefer the most complete and most business-realistic interpretation of the rows.
- Do not split one real product into multiple items.
- Do not invent data.
- Ignore subtotal/footer/helper/list sheets and non-item metadata rows.
- Keep only real requisition items.

Item rules:
- Product must contain the full item name.
- Part No, Manufacturer, MFG Model / Type, Catalog Code, Serial Number, Drawing Number must stay in their own columns where possible.
- Qty must be numeric string only.
- Unit must be unit text only, like PCS, SET, LOT, KIT, EA.
- ROB must be numeric string only.
- Quality should only be set when clearly present.
- Comments must contain note text only.

General rules:
- Extract only fields that are clearly present.
- RFQ Status may be missing in user files. Leave it empty if not shown.
- General Notes should contain only true top-level notes, not item fragments.

If there are multiple candidate interpretations, choose the one that best matches a real maritime procurement requisition table.
For PDF and image imports, prefer the interpretation that reconstructs realistic full item rows even when OCR text is fragmented.
PROMPT;
    }

    private function imageVisionPrompt(): string
    {
        return <<<'PROMPT'
You are extracting a maritime RFQ directly from an uploaded image.

Read the image itself as the primary source of truth.
Use OCR rows and OCR lines only as helpers when text is faint or fragmented.
Use template_aliases as strong hints only when they clearly match what is visible.

Return:
- general fields that are clearly visible
- the full item table

Rules:
- Reconstruct the actual item table from the image, not from guessed OCR fragments.
- Preserve the real visible item count. If 4 table rows are visible, return 4 items, not 2.
- Do not merge separate visible rows unless they are clearly one broken row split by OCR noise.
- Keep one real requisition item as one row.
- Product names must stay whole.
- Qty must be numeric string only.
- Unit must be only the unit text like PCS, SET, LOT, KIT.
- ROB must be numeric string only.
- Comments must stay as comments only.
- Leave fields empty instead of inventing data.
- Ignore decorative text, page titles, helper text, and footer text.
- RFQ Status may not exist in the source image. Leave it empty if absent.
- The first table column may be just a row number like "#". Ignore it.
- A row is still a valid item even if Part No or some middle columns are blank, as long as a product row is visibly present.
- Prefer exact table reading over aggressive cleanup.
PROMPT;
    }

    private function pdfVisionPrompt(): string
    {
        return <<<'PROMPT'
You are extracting a maritime RFQ from one or more page images rendered from an uploaded PDF.

Read the visible PDF page images as the primary source of truth.
Use OCR rows and OCR lines only as helpers when text is fragmented or faint.
Use template_aliases as strong hints only when they clearly match what is visible.

Return:
- general fields that are clearly visible
- the full item table

Rules:
- Reconstruct the actual requisition table from the visible PDF pages, not from guessed OCR fragments.
- Preserve the real visible item count. If multiple visible rows exist, return all real rows.
- Do not merge separate visible rows unless they are clearly one broken row split by OCR noise.
- Keep one real requisition item as one row.
- Product names must stay whole.
- Qty must be numeric string only.
- Unit must be only the unit text like PCS, SET, LOT, KIT.
- ROB must be numeric string only.
- Comments must stay as comments only.
- Leave fields empty instead of inventing data.
- Ignore page titles, decorative text, footers, and helper text unless they clearly belong to the requisition.
- RFQ Status may not exist in the PDF. Leave it empty if absent.
- The first table column may be just a row number like "#". Ignore it.
- A row is still a valid item even if Part No or some middle columns are blank, as long as a product row is visibly present.
- Prefer exact table reading over aggressive cleanup.
PROMPT;
    }

    private function consolidationSystemPrompt(): string
    {
        return <<<'PROMPT'
You are consolidating broken RFQ item rows.

You will receive a current item list that may be split incorrectly across adjacent rows.
Your job is to merge neighboring broken rows into the correct final logical items.

Rules:
- Keep the original item order.
- Prefer fewer, more complete rows over many broken rows.
- Merge adjacent fragments when they clearly belong to the same item.
- Product names may be split into multiple pieces across adjacent rows; combine them.
- Header leakage such as "Maker Ref", "ROB", "Notes", or similar should not appear as item values.
- Qty must be numeric string only.
- Unit must be short unit string only.
- ROB must be numeric string only.
- Comments must contain only notes/comment text.
- Do not invent values. Use OCR lines and neighboring item fragments only.
- If a field is truly missing, return an empty string.
PROMPT;
    }

    private function refinementSchema(): array
    {
        return [
            'name' => 'rfq_item_refinement',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => $this->itemSchema(),
                    ],
                ],
                'required' => ['items'],
            ],
        ];
    }

    private function recoverySchema(): array
    {
        return [
            'name' => 'rfq_document_recovery',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'general' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => collect(self::GENERAL_FIELDS)
                            ->mapWithKeys(fn ($field) => [$field => ['type' => 'string']])
                            ->all(),
                        'required' => self::GENERAL_FIELDS,
                    ],
                    'items' => [
                        'type' => 'array',
                        'items' => $this->itemSchema(),
                    ],
                ],
                'required' => ['general', 'items'],
            ],
        ];
    }

    private function itemSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => collect(self::ITEM_FIELDS)
                ->mapWithKeys(fn ($field) => [$field => ['type' => 'string']])
                ->all(),
            'required' => self::ITEM_FIELDS,
        ];
    }

    private function normalizeItem(mixed $item): array
    {
        $item = is_array($item) ? $item : [];

        return [
            'product_name' => $this->normalizeText($item['product_name'] ?? ''),
            'part_no' => $this->normalizeText($item['part_no'] ?? ''),
            'manufacturer' => $this->normalizeText($item['manufacturer'] ?? ''),
            'model_type' => $this->normalizeText($item['model_type'] ?? ''),
            'catalog_code' => $this->normalizeText($item['catalog_code'] ?? ''),
            'serial_number' => $this->normalizeText($item['serial_number'] ?? ''),
            'drawing_number' => $this->normalizeText($item['drawing_number'] ?? ''),
            'quantity' => $this->normalizeNumericText($item['quantity'] ?? ''),
            'unit' => strtoupper($this->normalizeText($item['unit'] ?? '')),
            'rob' => $this->normalizeNumericText($item['rob'] ?? ''),
            'quality' => strtolower($this->normalizeText($item['quality'] ?? '')),
            'comments' => $this->normalizeText($item['comments'] ?? ''),
            'files' => [],
        ];
    }

    private function mergeWithOriginalItem(array $refined, mixed $original): array
    {
        $original = is_array($original) ? $original : [];

        foreach (self::ITEM_FIELDS as $field) {
            $refinedValue = in_array($field, ['quantity', 'rob'], true)
                ? $this->normalizeNumericText($refined[$field] ?? '')
                : $this->normalizeText($refined[$field] ?? '');

            $originalValue = in_array($field, ['quantity', 'rob'], true)
                ? $this->normalizeNumericText($original[$field] ?? '')
                : $this->normalizeText($original[$field] ?? '');

            if ($refinedValue === '' && $originalValue !== '') {
                $refined[$field] = $field === 'unit'
                    ? strtoupper($originalValue)
                    : $originalValue;
            }
        }

        $refined['files'] = [];

        return $refined;
    }

    private function isMeaningfulItem(array $item): bool
    {
        $nonEmptyFields = collect(self::ITEM_FIELDS)
            ->map(fn ($field) => $this->normalizeText($item[$field] ?? ''))
            ->filter(fn ($value) => $value !== '' && $value !== '-')
            ->values();

        if ($nonEmptyFields->isEmpty()) {
            return false;
        }

        $product = $this->normalizeText($item['product_name'] ?? '');

        return $product !== '' || $nonEmptyFields->count() >= 3;
    }

    private function normalizeText(mixed $value): string
    {
        $string = trim((string) $value);

        if ($string === '-' || $string === '—') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $string);

        return $normalized === null ? '' : $normalized;
    }

    private function normalizeNumericText(mixed $value): string
    {
        $string = $this->normalizeText($value);

        if ($string === '') {
            return '';
        }

        $clean = preg_replace('/[^0-9.\-]+/', '', $string) ?? '';

        return trim($clean);
    }

    private function heuristicMergeAdjacentItems(array $items): array
    {
        $merged = [];
        $index = 0;

        while ($index < count($items)) {
            $current = $items[$index];
            $next = $items[$index + 1] ?? null;

            if ($next !== null && $this->shouldMergeAdjacentItems($current, $next)) {
                $merged[] = $this->mergeAdjacentItems($current, $next);
                $index += 2;
                continue;
            }

            $merged[] = $current;
            $index += 1;
        }

        return $merged;
    }

    private function shouldMergeAdjacentItems(array $current, array $next): bool
    {
        $currentQty = $this->normalizeNumericText($current['quantity'] ?? '');
        $currentUnit = $this->normalizeText($current['unit'] ?? '');
        $nextQty = $this->normalizeNumericText($next['quantity'] ?? '');
        $nextUnit = $this->normalizeText($next['unit'] ?? '');

        if ($currentQty !== '' || $currentUnit !== '') {
            return false;
        }

        if ($nextQty === '' && $nextUnit === '') {
            return false;
        }

        $currentStructured = collect([
            $current['part_no'] ?? '',
            $current['catalog_code'] ?? '',
            $current['serial_number'] ?? '',
            $current['drawing_number'] ?? '',
        ])->map(fn ($value) => $this->normalizeText($value))
            ->filter()
            ->count();

        if ($currentStructured >= 2) {
            return false;
        }

        $currentRichFields = collect([
            $current['part_no'] ?? '',
            $current['manufacturer'] ?? '',
            $current['model_type'] ?? '',
            $current['catalog_code'] ?? '',
            $current['serial_number'] ?? '',
            $current['drawing_number'] ?? '',
            $current['comments'] ?? '',
        ])->map(fn ($value) => $this->normalizeText($value))
            ->filter()
            ->count();

        if ($currentRichFields >= 2) {
            return false;
        }

        $nextStructuredStrength = collect([
            $next['part_no'] ?? '',
            $next['manufacturer'] ?? '',
            $next['model_type'] ?? '',
            $next['catalog_code'] ?? '',
            $next['serial_number'] ?? '',
            $next['drawing_number'] ?? '',
        ])->map(fn ($value) => $this->normalizeText($value))
            ->filter()
            ->count();

        if ($nextStructuredStrength === 0) {
            return false;
        }

        $currentProduct = $this->normalizeText($current['product_name'] ?? '');
        $nextProduct = $this->normalizeText($next['product_name'] ?? '');

        if ($currentProduct === '' || $nextProduct === '') {
            return false;
        }

        $currentWords = preg_split('/\s+/u', mb_strtolower($currentProduct)) ?: [];
        $nextWords = preg_split('/\s+/u', mb_strtolower($nextProduct)) ?: [];

        if (count($currentWords) > 3 || count($nextWords) > 4) {
            return false;
        }

        return ! preg_match('/[A-Z0-9]{2,}[-\/]?[A-Z0-9]{2,}/i', $currentProduct);
    }

    private function mergeAdjacentItems(array $current, array $next): array
    {
        $partNoCandidate = $this->pickBestCode([
            $current['part_no'] ?? '',
            $current['manufacturer'] ?? '',
            $current['model_type'] ?? '',
            $next['part_no'] ?? '',
        ]);

        $manufacturerCandidate = $this->pickBestManufacturer([
            $current['manufacturer'] ?? '',
            $next['manufacturer'] ?? '',
        ]);

        $modelCandidate = $this->pickBestModel([
            $current['model_type'] ?? '',
            $next['model_type'] ?? '',
        ]);

        return [
            'product_name' => $this->mergeProductFragments(
                $current['product_name'] ?? '',
                $next['product_name'] ?? ''
            ),
            'part_no' => $partNoCandidate,
            'manufacturer' => $manufacturerCandidate,
            'model_type' => $modelCandidate,
            'catalog_code' => $this->pickFirstMeaningful([
                $current['catalog_code'] ?? '',
                $next['catalog_code'] ?? '',
            ]),
            'serial_number' => $this->pickFirstMeaningful([
                $current['serial_number'] ?? '',
                $next['serial_number'] ?? '',
            ]),
            'drawing_number' => $this->pickFirstMeaningful([
                $current['drawing_number'] ?? '',
                $next['drawing_number'] ?? '',
            ]),
            'quantity' => $this->pickFirstMeaningful([
                $next['quantity'] ?? '',
                $current['quantity'] ?? '',
            ]),
            'unit' => strtoupper($this->pickFirstMeaningful([
                $next['unit'] ?? '',
                $current['unit'] ?? '',
            ])),
            'rob' => $this->pickFirstMeaningful([
                $next['rob'] ?? '',
                $current['rob'] ?? '',
            ]),
            'quality' => $this->pickFirstMeaningful([
                $next['quality'] ?? '',
                $current['quality'] ?? '',
            ]),
            'comments' => $this->pickBestComment([
                $current['comments'] ?? '',
                $next['comments'] ?? '',
                $current['model_type'] ?? '',
            ]),
            'files' => [],
        ];
    }

    private function mergeProductFragments(string $left, string $right): string
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);

        if ($left === '') {
            return $right;
        }

        if ($right === '') {
            return $left;
        }

        return trim($left.' '.$right);
    }

    private function pickBestCode(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeText($candidate);

            if ($candidate === '' || $this->looksHeaderLeak($candidate)) {
                continue;
            }

            if (preg_match('/[A-Z0-9]{2,}[-\/]?[A-Z0-9]{2,}/i', $candidate)) {
                return $candidate;
            }
        }

        return $this->pickFirstMeaningful($candidates);
    }

    private function pickBestManufacturer(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeText($candidate);

            if ($candidate === '' || $this->looksHeaderLeak($candidate) || preg_match('/\d/', $candidate)) {
                continue;
            }

            return $candidate;
        }

        return '';
    }

    private function pickBestModel(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeText($candidate);

            if ($candidate === '' || $this->looksHeaderLeak($candidate)) {
                continue;
            }

            return $candidate;
        }

        return '';
    }

    private function pickBestComment(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeText($candidate);

            if ($candidate === '' || $this->looksHeaderLeak($candidate) || preg_match('/^[A-Z0-9\-\/]+$/i', $candidate)) {
                continue;
            }

            return $candidate;
        }

        return '';
    }

    private function pickFirstMeaningful(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeText($candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function looksHeaderLeak(string $value): bool
    {
        $normalized = mb_strtolower($this->normalizeText($value));

        foreach (['maker ref', 'brand', 'model', 'qty', 'uom', 'rob', 'notes', 'remark', 'comment'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function looksHealthy(array $preview): bool
    {
        $items = collect($preview['items'] ?? []);

        if ($items->isEmpty()) {
            return false;
        }

        $badRows = $items->filter(function ($item) {
            $product = $this->normalizeText($item['product_name'] ?? '');
            $qty = $this->normalizeNumericText($item['quantity'] ?? '');
            $unit = $this->normalizeText($item['unit'] ?? '');
            $filled = collect(self::ITEM_FIELDS)
                ->filter(fn ($field) => $this->normalizeText($item[$field] ?? '') !== '')
                ->count();

            if ($filled <= 2) {
                return true;
            }

            if (strlen($product) > 0 && strlen($product) <= 4 && $qty === '' && $unit === '') {
                return true;
            }

            return false;
        })->count();

        $missingQtyUnit = $items->filter(function ($item) {
            $product = $this->normalizeText($item['product_name'] ?? '');

            return $product !== ''
                && $this->normalizeNumericText($item['quantity'] ?? '') === ''
                && $this->normalizeText($item['unit'] ?? '') === '';
        })->count();

        return $badRows === 0 && $missingQtyUnit <= max(1, (int) floor($items->count() / 3));
    }

    private function looksHealthierThan(array $candidate, array $baseline): bool
    {
        return $this->qualityScore($candidate) > $this->qualityScore($baseline);
    }

    private function shouldForceRecovery(array $preview): bool
    {
        $sourceType = (string) ($preview['summary']['source_type'] ?? '');

        if (! in_array($sourceType, ['pdf', 'image', 'document'], true)) {
            return false;
        }

        $items = collect($preview['items'] ?? []);

        if ($items->isEmpty()) {
            return false;
        }

        $missingMiddleColumns = $items->filter(function ($item) {
            $manufacturer = $this->normalizeText($item['manufacturer'] ?? '');
            $modelType = $this->normalizeText($item['model_type'] ?? '');
            $catalogCode = $this->normalizeText($item['catalog_code'] ?? '');
            $product = $this->normalizeText($item['product_name'] ?? '');
            $qty = $this->normalizeNumericText($item['quantity'] ?? '');

            return $product !== ''
                && $qty !== ''
                && $manufacturer === ''
                && $modelType === ''
                && $catalogCode === '';
        })->count();

        $headerLeaks = $items->filter(function ($item) {
            foreach (['product_name', 'part_no', 'manufacturer', 'model_type', 'catalog_code', 'comments'] as $field) {
                if ($this->looksHeaderLeak($item[$field] ?? '')) {
                    return true;
                }
            }

            return false;
        })->count();

        return $missingMiddleColumns >= max(1, (int) ceil($items->count() / 2))
            || $headerLeaks >= 1;
    }

    private function qualityScore(array $preview): int
    {
        $items = collect($preview['items'] ?? []);

        return $items->reduce(function ($score, $item) {
            $product = $this->normalizeText($item['product_name'] ?? '');
            $qty = $this->normalizeNumericText($item['quantity'] ?? '');
            $unit = $this->normalizeText($item['unit'] ?? '');
            $filled = collect(self::ITEM_FIELDS)
                ->filter(fn ($field) => $this->normalizeText($item[$field] ?? '') !== '')
                ->count();

            $score += min(8, $filled);

            if ($product !== '') {
                $score += strlen($product) >= 8 ? 4 : 1;
            }

            if ($qty !== '') {
                $score += 3;
            }

            if ($unit !== '') {
                $score += 2;
            }

            if ($this->normalizeNumericText($item['rob'] ?? '') !== '') {
                $score += 1;
            }

            if (strlen($product) > 0 && strlen($product) <= 4 && $qty === '' && $unit === '') {
                $score -= 6;
            }

            if ($filled <= 2) {
                $score -= 8;
            }

            return $score;
        }, 0);
    }
}
