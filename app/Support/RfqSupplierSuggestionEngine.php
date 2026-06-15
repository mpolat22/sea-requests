<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RfqSupplierSuggestionEngine
{
    private const BRAND_LIMIT = 6;
    private const CATEGORY_LIMIT = 5;
    private const SUBCATEGORY_LIMIT = 8;
    private const MAX_SUBCATEGORIES_PER_CATEGORY = 3;
    private const ROW_BRAND_LIMIT = 3;
    private const ROW_CATEGORY_LIMIT = 3;
    private const ROW_SUBCATEGORY_LIMIT = 4;
    private const AI_ROW_LIMIT = 6;
    private const AI_BRAND_CANDIDATE_LIMIT = 10;
    private const AI_CATEGORY_CANDIDATE_LIMIT = 8;
    private const AI_SUBCATEGORY_CANDIDATE_LIMIT = 12;

    private const IGNORED_TOKENS = [
        'and', 'for', 'the', 'with', 'from', 'your', 'this', 'that', 'into', 'onto', 'over',
        'under', 'part', 'parts', 'spare', 'spares', 'service', 'services', 'system', 'systems',
        'equipment', 'supplies', 'supply', 'support', 'general', 'marine', 'ship', 'ships',
        'items', 'item', 'request', 'requests', 'repair', 'repairs', 'solution', 'solutions',
        'product', 'products', 'assembly', 'kit', 'set', 'type', 'complete', 'pos', 'itemno',
        'order', 'designation',
    ];

    private const AMBIGUOUS_FREE_TEXT_BRANDS = [
        'crane', 'anchor', 'bridge', 'union', 'prime', 'best', 'king', 'eagle',
    ];

    private const BRAND_ALIASES = [
        'MAN B&W' => ['man b and w', 'man b w', 'man bw', 'b&w', 'b and w', 'man-b&w'],
        'Alfa Laval' => ['alfa-laval', 'alfa'],
        'MacGregor' => ['mac gregor'],
        'Fleetguard' => ['fleet guard'],
        'Bridon' => ['bri don'],
        'Wartsila' => ['wartsilae'],
        'Caterpillar' => ['cat'],
        'Mitsubishi' => ['mhi'],
    ];

    private const CATEGORY_ALIASES = [
        'Engine & Mechanical Systems' => [
            'piston ring', 'piston rings', 'cylinder liner', 'cylinder sleeve', 'fuel injection pump',
            'control rod', 'control sleeve', 'spring plate', 'locating bolt', 'pressure valve',
            'seal ring', 'hexagon bolt', 'hexagon nut', 'casing',
        ],
        'Oil, Fuel & Lubrication Systems' => [
            'fuel oil filter', 'fuel oil filter element', 'oil filter element', 'fuel filter element',
            'fuel injection pump', 'lubrication oil', 'separator oil',
        ],
        'Filters' => [
            'filter element', 'filter cartridge', 'oil filter', 'fuel filter', 'strainer element',
        ],
        'Hydraulic Systems & Power Packs' => [
            'hydraulic cylinder', 'hydraulic pump', 'power pack', 'accumulator',
        ],
        'Cranes, Winches & Lifting' => [
            'deck crane', 'crane wire rope', 'hook block', 'hoist rope', 'lifting wire',
        ],
        'Mooring, Ropes, Wires & Cables' => [
            'wire rope', 'steel wire rope', 'mooring rope', 'wire sling',
        ],
        'Deck, Cargo & Lashing Equipment' => [
            'deck crane', 'cargo securing', 'lashing gear',
        ],
    ];

    private const SUBCATEGORY_ALIASES = [
        'Piston Ring' => ['piston ring', 'piston ring set'],
        'Engine Piston Rings' => ['piston ring set', 'piston rings'],
        'Engine Piston Oil Control Ring' => ['oil control ring'],
        'Alu-Coated Piston Ring' => ['alu coated piston ring', 'aluminium coated piston ring'],
        'Cylinder' => ['cylinder liner', 'cylinder sleeve'],
        'Cylinder Liner Housing' => ['cylinder liner housing'],
        'Cylinder Liner Polishing Ring' => ['cylinder liner polishing ring'],
        'Oil Filter Element' => ['fuel oil filter element', 'oil filter element', 'filter element'],
        'Fuel Injection Pump' => ['fuel injection pump'],
        'Wire Rope' => ['deck crane wire rope', 'wire rope', 'steel wire rope'],
        'Pressure Valve' => ['pressure valve'],
        'Seal Ring' => ['seal ring'],
    ];

    public function suggest(array $payload): array
    {
        $rows = $this->extractRows($payload);

        if ($rows === []) {
            return $this->emptyResult('Add item names, brands, model details, or service text first so we can suggest supplier filters.');
        }

        $rowSuggestions = array_map(fn (array $row) => $this->buildDeterministicRowSuggestion($row), $rows);
        $documentContext = $this->buildDocumentContext($rowSuggestions);
        $rowSuggestions = $this->inheritDocumentContext($rowSuggestions, $documentContext);
        $rowSuggestions = $this->applyAiFallback($rowSuggestions, $documentContext);
        $documentContext = $this->buildDocumentContext($rowSuggestions);

        $brands = $this->aggregateEntries($rowSuggestions, 'brands', self::BRAND_LIMIT);
        $categories = $this->aggregateEntries($rowSuggestions, 'categories', self::CATEGORY_LIMIT);
        $subcategories = $this->aggregateEntries($rowSuggestions, 'subcategories', self::SUBCATEGORY_LIMIT, true);
        $categories = $this->ensureCategoriesFromSubcategories($categories, $subcategories);

        if ($brands === [] && $categories === [] && $subcategories === []) {
            return $this->emptyResult('We could not detect a reliable supplier filter from the current request yet. Add clearer brand, product, model, or catalog details and try again.');
        }

        return [
            'filters' => [
                'category_ids' => array_values(array_map(fn (array $entry) => (int) $entry['id'], $categories)),
                'subcategory_ids' => array_values(array_map(fn (array $entry) => (int) $entry['id'], $subcategories)),
                'brand_ids' => array_values(array_map(fn (array $entry) => (int) $entry['id'], $brands)),
            ],
            'brands' => $this->publicEntries($brands),
            'categories' => $this->publicEntries($categories),
            'subcategories' => $this->publicEntries($subcategories),
            'row_suggestions' => array_map(fn (array $row) => $this->publicRowSuggestion($row), $rowSuggestions),
            'document_context' => [
                'brands' => $this->publicEntries($documentContext['brands'] ?? []),
                'categories' => $this->publicEntries($documentContext['categories'] ?? []),
                'subcategories' => $this->publicEntries($documentContext['subcategories'] ?? []),
            ],
            'summary' => $this->summaryText($brands, $categories, $subcategories),
            'empty_message' => null,
        ];
    }

    private function emptyResult(string $message): array
    {
        return [
            'filters' => [
                'category_ids' => [],
                'subcategory_ids' => [],
                'brand_ids' => [],
            ],
            'brands' => [],
            'categories' => [],
            'subcategories' => [],
            'row_suggestions' => [],
            'document_context' => [
                'brands' => [],
                'categories' => [],
                'subcategories' => [],
            ],
            'summary' => null,
            'empty_message' => $message,
        ];
    }

    private function extractRows(array $payload): array
    {
        $requestType = $payload['request_type'] ?? 'spare_parts';

        if ($requestType === 'service_request') {
            $title = trim((string) ($payload['service_title'] ?? ''));
            $description = trim((string) ($payload['service_description'] ?? ''));
            $combined = trim(implode(' ', array_filter([$title, $description])));

            if ($combined === '') {
                return [];
            }

            return [$this->buildRow(
                rowIndex: 0,
                source: $title !== '' ? $title : 'Service request',
                text: $combined,
                manufacturer: '',
                referenceText: $title,
            )];
        }

        $rows = [];

        foreach (($payload['items'] ?? []) as $index => $item) {
            $productName = trim((string) ($item['product_name'] ?? ''));
            $manufacturer = trim((string) ($item['manufacturer'] ?? ''));
            $modelType = trim((string) ($item['model_type'] ?? ''));
            $catalogCode = trim((string) ($item['catalog_code'] ?? ''));
            $partNo = trim((string) ($item['part_no'] ?? ''));
            $comments = trim((string) ($item['comments'] ?? ''));

            $combined = trim(implode(' ', array_filter([
                $productName,
                $manufacturer,
                $modelType,
                $catalogCode,
                $partNo,
                $comments,
            ])));

            if ($combined === '') {
                continue;
            }

            $rows[] = $this->buildRow(
                rowIndex: (int) $index,
                source: $productName !== '' ? $productName : ($manufacturer !== '' ? $manufacturer : 'RFQ item'),
                text: $combined,
                manufacturer: $manufacturer,
                referenceText: trim(implode(' ', array_filter([$modelType, $catalogCode, $partNo]))),
            );
        }

        return $rows;
    }

    private function buildRow(int $rowIndex, string $source, string $text, string $manufacturer, string $referenceText): array
    {
        $normalized = $this->normalize($text);

        return [
            'row_index' => $rowIndex,
            'source' => $source,
            'text' => $text,
            'manufacturer' => $manufacturer,
            'reference_text' => $referenceText,
            'manufacturer_normalized' => $this->normalize($manufacturer),
            'reference_normalized' => $this->normalize($referenceText),
            'normalized' => $normalized,
            'compact' => $this->compact($text),
            'tokens' => $this->tokenize($normalized),
        ];
    }

    private function buildDeterministicRowSuggestion(array $row): array
    {
        $brands = $this->brandMatchesForRow($row);
        [$categories, $subcategories] = $this->taxonomyMatchesForRow($row);

        return [
            'row_index' => $row['row_index'],
            'source' => $row['source'],
            'text' => $row['text'],
            'manufacturer' => $row['manufacturer'],
            'reference_text' => $row['reference_text'],
            'manufacturer_normalized' => $row['manufacturer_normalized'] ?? '',
            'reference_normalized' => $row['reference_normalized'] ?? '',
            'normalized' => $row['normalized'] ?? '',
            'compact' => $row['compact'] ?? '',
            'tokens' => $row['tokens'] ?? [],
            'brands' => $brands,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'confidence' => $this->rowConfidenceMeta($brands, $categories, $subcategories),
        ];
    }

    private function brandMatchesForRow(array $row): array
    {
        $matches = [];

        foreach ($this->brandSnapshot() as $brand) {
            [$score, $reason, $method] = $this->scoreBrandMatch($brand, $row);

            if ($score <= 0) {
                continue;
            }

            $matches[] = $this->makeEntry([
                'id' => $brand['id'],
                'name' => $brand['name'],
                'source' => $row['source'],
                'reason' => $reason,
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => $method,
            ]);
        }

        return collect($matches)
            ->sortByDesc('score')
            ->take(self::ROW_BRAND_LIMIT)
            ->values()
            ->all();
    }

    private function taxonomyMatchesForRow(array $row): array
    {
        $categoryMatches = [];
        $subcategoryMatches = [];
        $categoriesById = [];

        foreach ($this->categorySnapshot() as $category) {
            $categoriesById[$category['id']] = $category;

            [$score, $reason, $method] = $this->scoreCandidateMatch($category, $row, 150, 260, 320);

            if ($score <= 0) {
                continue;
            }

            $categoryMatches[$category['id']] = $this->makeEntry([
                'id' => $category['id'],
                'name' => $category['name'],
                'source' => $row['source'],
                'reason' => $reason,
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => $method,
            ]);
        }

        foreach ($this->subcategorySnapshot() as $subcategory) {
            [$score, $reason, $method] = $this->scoreCandidateMatch($subcategory, $row, 180, 300, 360);

            if ($score <= 0) {
                continue;
            }

            $subcategoryMatches[$subcategory['id']] = $this->makeEntry([
                'id' => $subcategory['id'],
                'name' => $subcategory['name'],
                'category_id' => $subcategory['category_id'],
                'category_name' => $subcategory['category_name'],
                'source' => $row['source'],
                'reason' => $reason,
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => $method,
            ]);
        }

        $selectedSubcategories = [];
        $subcategoriesPerCategory = [];

        foreach (collect($subcategoryMatches)->sortByDesc('score') as $entry) {
            $categoryId = (int) $entry['category_id'];
            $subcategoriesPerCategory[$categoryId] = $subcategoriesPerCategory[$categoryId] ?? 0;

            if ($subcategoriesPerCategory[$categoryId] >= self::MAX_SUBCATEGORIES_PER_CATEGORY) {
                continue;
            }

            $selectedSubcategories[] = $entry;
            $subcategoriesPerCategory[$categoryId]++;

            if (count($selectedSubcategories) >= self::ROW_SUBCATEGORY_LIMIT) {
                break;
            }
        }

        foreach ($selectedSubcategories as $subcategory) {
            $categoryId = (int) $subcategory['category_id'];
            $existing = $categoryMatches[$categoryId] ?? null;
            $boostedScore = (int) ($subcategory['score'] + 90);

            if (! $existing || $boostedScore >= (int) $existing['score']) {
                $category = $categoriesById[$categoryId] ?? null;

                if (! $category) {
                    continue;
                }

                $categoryMatches[$categoryId] = $this->makeEntry([
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'source' => $subcategory['source'],
                    'reason' => "Matched from {$subcategory['name']}.",
                    'score' => $boostedScore,
                    'confidence' => $this->confidenceFromScore($boostedScore),
                    'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($boostedScore)),
                    'method' => 'derived_subcategory',
                ]);
            }
        }

        $selectedCategories = collect($categoryMatches)
            ->sortByDesc('score')
            ->take(self::ROW_CATEGORY_LIMIT)
            ->values()
            ->all();

        $selectedCategoryIds = collect($selectedCategories)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->values();

        $selectedSubcategories = collect($selectedSubcategories)
            ->filter(fn (array $entry) => $selectedCategoryIds->contains((int) $entry['category_id']))
            ->values()
            ->all();

        return [$selectedCategories, $selectedSubcategories];
    }

    private function buildDocumentContext(array $rowSuggestions): array
    {
        return [
            'brands' => $this->strongContextEntries($rowSuggestions, 'brands', self::ROW_BRAND_LIMIT),
            'categories' => $this->strongContextEntries($rowSuggestions, 'categories', self::ROW_CATEGORY_LIMIT),
            'subcategories' => $this->strongContextEntries($rowSuggestions, 'subcategories', self::ROW_SUBCATEGORY_LIMIT, true),
        ];
    }

    private function strongContextEntries(array $rowSuggestions, string $key, int $limit, bool $subcategoryMode = false): array
    {
        $pool = [];

        foreach ($rowSuggestions as $row) {
            foreach (($row[$key] ?? []) as $entry) {
                $id = (int) $entry['id'];
                $current = $pool[$id] ?? null;

                if (! $current) {
                    $pool[$id] = $entry + ['_count' => 1];
                    continue;
                }

                $pool[$id]['_count']++;

                if ((int) $entry['score'] > (int) $current['score']) {
                    $pool[$id] = array_merge($entry, ['_count' => $pool[$id]['_count']]);
                }
            }
        }

        $entries = collect($pool)
            ->filter(fn (array $entry) => ((int) ($entry['_count'] ?? 0) >= 2) || ((int) ($entry['confidence'] ?? 0) >= 86))
            ->sortByDesc(fn (array $entry) => ((int) ($entry['confidence'] ?? 0) * 10) + (int) ($entry['_count'] ?? 0))
            ->values()
            ->all();

        if ($subcategoryMode) {
            $selected = [];
            $perCategory = [];

            foreach ($entries as $entry) {
                $categoryId = (int) ($entry['category_id'] ?? 0);
                $perCategory[$categoryId] = $perCategory[$categoryId] ?? 0;

                if ($categoryId !== 0 && $perCategory[$categoryId] >= self::MAX_SUBCATEGORIES_PER_CATEGORY) {
                    continue;
                }

                $selected[] = $entry;

                if ($categoryId !== 0) {
                    $perCategory[$categoryId]++;
                }

                if (count($selected) >= $limit) {
                    break;
                }
            }

            return array_map(fn (array $entry) => $this->stripInternalEntry($entry), $selected);
        }

        return array_map(
            fn (array $entry) => $this->stripInternalEntry($entry),
            array_slice($entries, 0, $limit)
        );
    }

    private function inheritDocumentContext(array $rowSuggestions, array $documentContext): array
    {
        $contextBrands = $documentContext['brands'] ?? [];

        if ($contextBrands === [] || count($contextBrands) > 2) {
            return $rowSuggestions;
        }

        foreach ($rowSuggestions as $index => $row) {
            if (($row['brands'] ?? []) !== []) {
                continue;
            }

            $inherited = [];

            foreach ($contextBrands as $brand) {
                $confidence = max(58, min(76, (int) ($brand['confidence'] ?? 0) - 14));
                $score = $this->scoreFromConfidence($confidence);

                $inherited[] = $this->makeEntry([
                    'id' => $brand['id'],
                    'name' => $brand['name'],
                    'source' => $row['source'],
                    'reason' => 'Inherited from the surrounding request context.',
                    'score' => $score,
                    'confidence' => $confidence,
                    'confidence_label' => $this->confidenceLabel($confidence),
                    'method' => 'context_inherited',
                ]);
            }

            $rowSuggestions[$index]['brands'] = $inherited;
            $rowSuggestions[$index]['confidence'] = $this->rowConfidenceMeta(
                $rowSuggestions[$index]['brands'],
                $rowSuggestions[$index]['categories'],
                $rowSuggestions[$index]['subcategories']
            );
        }

        return $rowSuggestions;
    }

    private function applyAiFallback(array $rowSuggestions, array $documentContext): array
    {
        if (! $this->isAiEnabled()) {
            return $rowSuggestions;
        }

        $targets = collect($rowSuggestions)
            ->filter(fn (array $row) => $this->needsAiFallback($row))
            ->take(self::AI_ROW_LIMIT)
            ->values();

        if ($targets->isEmpty()) {
            return $rowSuggestions;
        }

        $response = $this->requestAiSuggestions([
            'document_context' => [
                'brands' => $this->compactEntryPayload($documentContext['brands'] ?? []),
                'categories' => $this->compactEntryPayload($documentContext['categories'] ?? []),
                'subcategories' => $this->compactEntryPayload($documentContext['subcategories'] ?? []),
            ],
            'rows' => $targets->map(function (array $row) use ($documentContext) {
                return [
                    'row_index' => $row['row_index'],
                    'source' => $row['source'],
                    'text' => $row['text'],
                    'manufacturer' => $row['manufacturer'],
                    'reference_text' => $row['reference_text'],
                    'existing' => [
                        'brands' => $this->compactEntryPayload($row['brands'] ?? []),
                        'categories' => $this->compactEntryPayload($row['categories'] ?? []),
                        'subcategories' => $this->compactEntryPayload($row['subcategories'] ?? []),
                    ],
                    'candidates' => [
                        'brands' => $this->compactEntryPayload($this->topBrandCandidatesForAi($row, $documentContext)),
                        'categories' => $this->compactEntryPayload($this->topCategoryCandidatesForAi($row, $documentContext)),
                        'subcategories' => $this->compactEntryPayload($this->topSubcategoryCandidatesForAi($row, $documentContext)),
                    ],
                ];
            })->all(),
        ]);

        if (! is_array($response) || ! is_array($response['rows'] ?? null)) {
            return $rowSuggestions;
        }

        $brandsById = collect($this->brandSnapshot())->keyBy('id');
        $categoriesById = collect($this->categorySnapshot())->keyBy('id');
        $subcategoriesById = collect($this->subcategorySnapshot())->keyBy('id');

        foreach ($response['rows'] as $aiRow) {
            $rowIndex = (int) ($aiRow['row_index'] ?? -1);
            $targetIndex = collect($rowSuggestions)->search(fn (array $row) => (int) $row['row_index'] === $rowIndex);

            if ($targetIndex === false) {
                continue;
            }

            $confidence = max(0, min(100, (int) ($aiRow['confidence'] ?? 0)));

            if ($confidence < 45) {
                continue;
            }

            $reason = trim((string) ($aiRow['reason'] ?? 'AI inferred this from the item wording and the surrounding request context.'));
            $score = $this->scoreFromConfidence($confidence);

            $brandEntries = $this->entriesFromAiIds(
                Arr::wrap($aiRow['brand_ids'] ?? []),
                $brandsById->all(),
                $rowSuggestions[$targetIndex]['source'],
                $reason,
                $score,
                $confidence,
                'ai'
            );
            $categoryEntries = $this->entriesFromAiIds(
                Arr::wrap($aiRow['category_ids'] ?? []),
                $categoriesById->all(),
                $rowSuggestions[$targetIndex]['source'],
                $reason,
                $score,
                $confidence,
                'ai'
            );
            $subcategoryEntries = $this->entriesFromAiIds(
                Arr::wrap($aiRow['subcategory_ids'] ?? []),
                $subcategoriesById->all(),
                $rowSuggestions[$targetIndex]['source'],
                $reason,
                $score,
                $confidence,
                'ai'
            );

            $rowSuggestions[$targetIndex]['brands'] = $this->mergeSuggestionEntries(
                $rowSuggestions[$targetIndex]['brands'] ?? [],
                $brandEntries,
                self::ROW_BRAND_LIMIT
            );
            $rowSuggestions[$targetIndex]['categories'] = $this->mergeSuggestionEntries(
                $rowSuggestions[$targetIndex]['categories'] ?? [],
                $categoryEntries,
                self::ROW_CATEGORY_LIMIT
            );
            $rowSuggestions[$targetIndex]['subcategories'] = $this->mergeSuggestionEntries(
                $rowSuggestions[$targetIndex]['subcategories'] ?? [],
                $subcategoryEntries,
                self::ROW_SUBCATEGORY_LIMIT,
                true
            );
            $rowSuggestions[$targetIndex]['categories'] = $this->ensureCategoriesFromSubcategories(
                $rowSuggestions[$targetIndex]['categories'],
                $rowSuggestions[$targetIndex]['subcategories']
            );
            $rowSuggestions[$targetIndex]['confidence'] = $this->rowConfidenceMeta(
                $rowSuggestions[$targetIndex]['brands'],
                $rowSuggestions[$targetIndex]['categories'],
                $rowSuggestions[$targetIndex]['subcategories']
            );
        }

        return $rowSuggestions;
    }

    private function needsAiFallback(array $row): bool
    {
        if (mb_strlen((string) ($row['text'] ?? '')) < 6) {
            return false;
        }

        return ($row['brands'] ?? []) === []
            || ($row['categories'] ?? []) === []
            || ($row['subcategories'] ?? []) === []
            || (int) ($row['confidence']['score'] ?? 0) < 68;
    }

    private function topBrandCandidatesForAi(array $row, array $documentContext): array
    {
        $pool = [];

        foreach ($this->brandSnapshot() as $brand) {
            [$score, $reason, $method] = $this->scoreBrandMatch($brand, $row);

            if ($score <= 0) {
                continue;
            }

            $pool[$brand['id']] = $this->makeEntry([
                'id' => $brand['id'],
                'name' => $brand['name'],
                'reason' => $reason,
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => $method,
            ]);
        }

        foreach (($documentContext['brands'] ?? []) as $brand) {
            $existing = $pool[$brand['id']] ?? null;
            $score = max((int) ($existing['score'] ?? 0), max(150, (int) (($brand['score'] ?? 0) - 80)));

            $pool[$brand['id']] = $this->makeEntry(array_merge($brand, [
                'reason' => 'Context candidate from other request lines.',
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => 'context_candidate',
            ]));
        }

        return collect($pool)
            ->sortByDesc('score')
            ->take(self::AI_BRAND_CANDIDATE_LIMIT)
            ->values()
            ->all();
    }

    private function topCategoryCandidatesForAi(array $row, array $documentContext): array
    {
        $pool = [];

        foreach ($this->categorySnapshot() as $category) {
            [$score, $reason, $method] = $this->scoreCandidateMatch($category, $row, 0, 260, 320);

            if ($score <= 0) {
                continue;
            }

            $pool[$category['id']] = $this->makeEntry([
                'id' => $category['id'],
                'name' => $category['name'],
                'reason' => $reason,
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => $method,
            ]);
        }

        foreach (($documentContext['categories'] ?? []) as $category) {
            $existing = $pool[$category['id']] ?? null;
            $score = max((int) ($existing['score'] ?? 0), max(140, (int) (($category['score'] ?? 0) - 90)));

            $pool[$category['id']] = $this->makeEntry(array_merge($category, [
                'reason' => 'Context candidate from other request lines.',
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => 'context_candidate',
            ]));
        }

        return collect($pool)
            ->sortByDesc('score')
            ->take(self::AI_CATEGORY_CANDIDATE_LIMIT)
            ->values()
            ->all();
    }

    private function topSubcategoryCandidatesForAi(array $row, array $documentContext): array
    {
        $pool = [];

        foreach ($this->subcategorySnapshot() as $subcategory) {
            [$score, $reason, $method] = $this->scoreCandidateMatch($subcategory, $row, 0, 300, 360);

            if ($score <= 0) {
                continue;
            }

            $pool[$subcategory['id']] = $this->makeEntry([
                'id' => $subcategory['id'],
                'name' => $subcategory['name'],
                'category_id' => $subcategory['category_id'],
                'category_name' => $subcategory['category_name'],
                'reason' => $reason,
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => $method,
            ]);
        }

        foreach (($documentContext['subcategories'] ?? []) as $subcategory) {
            $existing = $pool[$subcategory['id']] ?? null;
            $score = max((int) ($existing['score'] ?? 0), max(150, (int) (($subcategory['score'] ?? 0) - 90)));

            $pool[$subcategory['id']] = $this->makeEntry(array_merge($subcategory, [
                'reason' => 'Context candidate from other request lines.',
                'score' => $score,
                'confidence' => $this->confidenceFromScore($score),
                'confidence_label' => $this->confidenceLabel($this->confidenceFromScore($score)),
                'method' => 'context_candidate',
            ]));
        }

        $selected = [];
        $perCategory = [];

        foreach (collect($pool)->sortByDesc('score') as $entry) {
            $categoryId = (int) ($entry['category_id'] ?? 0);
            $perCategory[$categoryId] = $perCategory[$categoryId] ?? 0;

            if ($categoryId !== 0 && $perCategory[$categoryId] >= self::MAX_SUBCATEGORIES_PER_CATEGORY) {
                continue;
            }

            $selected[] = $entry;

            if ($categoryId !== 0) {
                $perCategory[$categoryId]++;
            }

            if (count($selected) >= self::AI_SUBCATEGORY_CANDIDATE_LIMIT) {
                break;
            }
        }

        return $selected;
    }

    private function entriesFromAiIds(
        array $ids,
        array $snapshotById,
        string $source,
        string $reason,
        int $score,
        int $confidence,
        string $method
    ): array {
        return collect($ids)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->unique()
            ->map(function (int $id) use ($snapshotById, $source, $reason, $score, $confidence, $method) {
                $snapshot = $snapshotById[$id] ?? null;

                if (! $snapshot) {
                    return null;
                }

                return $this->makeEntry([
                    'id' => (int) $snapshot['id'],
                    'name' => (string) $snapshot['name'],
                    'category_id' => isset($snapshot['category_id']) ? (int) $snapshot['category_id'] : null,
                    'category_name' => $snapshot['category_name'] ?? null,
                    'source' => $source,
                    'reason' => $reason,
                    'score' => $score,
                    'confidence' => $confidence,
                    'confidence_label' => $this->confidenceLabel($confidence),
                    'method' => $method,
                ]);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function mergeSuggestionEntries(array $existing, array $incoming, int $limit, bool $subcategoryMode = false): array
    {
        $pool = [];

        foreach (array_merge($existing, $incoming) as $entry) {
            $id = (int) ($entry['id'] ?? 0);

            if ($id === 0) {
                continue;
            }

            $current = $pool[$id] ?? null;

            if (! $current || (int) ($entry['score'] ?? 0) > (int) ($current['score'] ?? 0)) {
                $pool[$id] = $entry;
            }
        }

        $entries = collect($pool)
            ->sortByDesc('score')
            ->values()
            ->all();

        if (! $subcategoryMode) {
            return array_slice($entries, 0, $limit);
        }

        $selected = [];
        $perCategory = [];

        foreach ($entries as $entry) {
            $categoryId = (int) ($entry['category_id'] ?? 0);
            $perCategory[$categoryId] = $perCategory[$categoryId] ?? 0;

            if ($categoryId !== 0 && $perCategory[$categoryId] >= self::MAX_SUBCATEGORIES_PER_CATEGORY) {
                continue;
            }

            $selected[] = $entry;

            if ($categoryId !== 0) {
                $perCategory[$categoryId]++;
            }

            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }

    private function aggregateEntries(array $rowSuggestions, string $key, int $limit, bool $subcategoryMode = false): array
    {
        $pool = [];

        foreach ($rowSuggestions as $row) {
            foreach (($row[$key] ?? []) as $entry) {
                $id = (int) ($entry['id'] ?? 0);

                if ($id === 0) {
                    continue;
                }

                $current = $pool[$id] ?? null;

                if (! $current || (int) ($entry['score'] ?? 0) > (int) ($current['score'] ?? 0)) {
                    $pool[$id] = $entry;
                }
            }
        }

        $entries = collect($pool)
            ->sortByDesc('score')
            ->values()
            ->all();

        if (! $subcategoryMode) {
            return array_slice($entries, 0, $limit);
        }

        $selected = [];
        $perCategory = [];

        foreach ($entries as $entry) {
            $categoryId = (int) ($entry['category_id'] ?? 0);
            $perCategory[$categoryId] = $perCategory[$categoryId] ?? 0;

            if ($categoryId !== 0 && $perCategory[$categoryId] >= self::MAX_SUBCATEGORIES_PER_CATEGORY) {
                continue;
            }

            $selected[] = $entry;

            if ($categoryId !== 0) {
                $perCategory[$categoryId]++;
            }

            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }

    private function ensureCategoriesFromSubcategories(array $categories, array $subcategories): array
    {
        $categoryIds = collect($categories)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->values();

        if ($subcategories === []) {
            return $categories;
        }

        $categoriesById = collect($this->categorySnapshot())->keyBy('id');

        foreach ($subcategories as $subcategory) {
            $categoryId = (int) ($subcategory['category_id'] ?? 0);

            if ($categoryId === 0 || $categoryIds->contains($categoryId)) {
                continue;
            }

            $snapshot = $categoriesById->get($categoryId);

            if (! $snapshot) {
                continue;
            }

            $score = max((int) ($subcategory['score'] ?? 0) - 25, 140);
            $confidence = $this->confidenceFromScore($score);

            $categories[] = $this->makeEntry([
                'id' => (int) $snapshot['id'],
                'name' => (string) $snapshot['name'],
                'source' => $subcategory['source'] ?? null,
                'reason' => "Matched from {$subcategory['name']}.",
                'score' => $score,
                'confidence' => $confidence,
                'confidence_label' => $this->confidenceLabel($confidence),
                'method' => 'derived_subcategory',
            ]);
            $categoryIds->push($categoryId);
        }

        return collect($categories)
            ->sortByDesc('score')
            ->take(self::CATEGORY_LIMIT)
            ->values()
            ->all();
    }

    private function publicEntries(array $entries): array
    {
        return array_map(fn (array $entry) => $this->stripInternalEntry($entry), $entries);
    }

    private function publicRowSuggestion(array $row): array
    {
        return [
            'row_index' => (int) $row['row_index'],
            'source' => $row['source'],
            'text' => $row['text'],
            'confidence' => $row['confidence'],
            'brands' => $this->publicEntries($row['brands'] ?? []),
            'categories' => $this->publicEntries($row['categories'] ?? []),
            'subcategories' => $this->publicEntries($row['subcategories'] ?? []),
        ];
    }

    private function stripInternalEntry(array $entry): array
    {
        unset($entry['score'], $entry['method'], $entry['_count']);

        return $entry;
    }

    private function compactEntryPayload(array $entries): array
    {
        return collect($entries)
            ->map(fn (array $entry) => array_filter([
                'id' => (int) ($entry['id'] ?? 0),
                'name' => (string) ($entry['name'] ?? ''),
                'category_id' => isset($entry['category_id']) ? (int) $entry['category_id'] : null,
                'category_name' => $entry['category_name'] ?? null,
                'confidence' => isset($entry['confidence']) ? (int) $entry['confidence'] : null,
            ], fn ($value) => $value !== null && $value !== '' && $value !== 0))
            ->values()
            ->all();
    }

    private function rowConfidenceMeta(array $brands, array $categories, array $subcategories): array
    {
        $topConfidence = collect([$brands, $categories, $subcategories])
            ->flatten(1)
            ->map(fn (array $entry) => (int) ($entry['confidence'] ?? 0))
            ->max();

        $score = max(0, (int) $topConfidence);

        return [
            'score' => $score,
            'label' => $this->confidenceLabel($score),
        ];
    }

    private function scoreBrandMatch(array $brand, array $row): array
    {
        if (($brand['normalized'] ?? '') === '') {
            return [0, null, null];
        }

        $manufacturerNormalized = (string) ($row['manufacturer_normalized'] ?? '');
        $referenceNormalized = (string) ($row['reference_normalized'] ?? '');
        $normalized = (string) ($row['normalized'] ?? '');
        $compact = (string) ($row['compact'] ?? '');

        foreach (($brand['aliases'] ?? []) as $alias) {
            if ($manufacturerNormalized !== '' && $this->containsPhrase($manufacturerNormalized, $alias)) {
                return [520, 'Detected from the manufacturer field.', 'manufacturer'];
            }
        }

        foreach (($brand['aliases'] ?? []) as $alias) {
            if ($referenceNormalized !== '' && $this->containsPhrase($referenceNormalized, $alias)) {
                return [320, 'Detected from model or catalog details.', 'reference'];
            }
        }

        if (($brand['short'] ?? false) || ($brand['ambiguous'] ?? false)) {
            return [0, null, null];
        }

        foreach (($brand['aliases'] ?? []) as $alias) {
            if (mb_strlen($alias) >= 5 && $this->containsPhrase($normalized, $alias)) {
                return [230, 'Detected from the item text.', 'text'];
            }

            $compactAlias = str_replace(' ', '', $alias);

            if (strlen($compactAlias) >= 5 && str_contains($compact, $compactAlias)) {
                return [190, 'Detected from the item text.', 'text'];
            }
        }

        return [0, null, null];
    }

    private function scoreCandidateMatch(array $candidate, array $row, int $minimumScore, int $exactPhraseScore, int $aliasPhraseScore): array
    {
        if (($candidate['normalized'] ?? '') === '') {
            return [0, null, null];
        }

        $score = 0;
        $reason = null;
        $method = null;
        $matchedTokens = [];

        if (mb_strlen((string) $candidate['normalized']) >= 7 && $this->containsPhrase($row['normalized'], $candidate['normalized'])) {
            $score = max($score, $exactPhraseScore);
            $reason = 'Matched from the request text.';
            $method = 'exact_phrase';
        }

        foreach (($candidate['aliases'] ?? []) as $alias) {
            if ($this->containsPhrase($row['normalized'], $alias)) {
                $score = max($score, $aliasPhraseScore);
                $reason = 'Matched from a marine item alias.';
                $method = 'alias_phrase';
                break;
            }
        }

        foreach (($candidate['tokens'] ?? []) as $token) {
            if (in_array($token, $row['tokens'], true)) {
                $matchedTokens[] = $token;
            }
        }

        $matchedTokens = array_values(array_unique($matchedTokens));

        if (count($matchedTokens) >= 2) {
            $tokenScore = 120 + (count($matchedTokens) * 30);

            if ($tokenScore > $score) {
                $score = $tokenScore;
                $reason = 'Matched terms: '.implode(', ', array_slice($matchedTokens, 0, 3)).'.';
                $method = 'token_match';
            }
        } elseif (count($matchedTokens) === 1 && mb_strlen($matchedTokens[0]) >= 7) {
            $tokenScore = 100;

            if ($tokenScore > $score) {
                $score = $tokenScore;
                $reason = 'Matched from a distinctive term in the request text.';
                $method = 'single_token';
            }
        }

        if ($score < $minimumScore) {
            return [0, null, null];
        }

        return [$score, $reason ?? 'Matched from the request text.', $method ?? 'text'];
    }

    private function brandSnapshot(): array
    {
        return Cache::store('file')->remember('rfq_supplier_suggestion_brands_v2', now()->addMinutes(30), function (): array {
            return Brand::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(function (Brand $brand) {
                    $normalized = $this->normalize($brand->name);

                    return [
                        'id' => (int) $brand->id,
                        'name' => $brand->name,
                        'normalized' => $normalized,
                        'aliases' => $this->brandAliasesForName($brand->name),
                        'short' => mb_strlen($normalized) <= 3,
                        'ambiguous' => in_array($normalized, self::AMBIGUOUS_FREE_TEXT_BRANDS, true),
                    ];
                })
                ->values()
                ->all();
        });
    }

    private function categorySnapshot(): array
    {
        return Cache::store('file')->remember('rfq_supplier_suggestion_categories_v2', now()->addMinutes(30), function (): array {
            return Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $category) => [
                    'id' => (int) $category->id,
                    'name' => $category->name,
                    'normalized' => $this->normalize($category->name),
                    'tokens' => $this->distinctiveTokens($category->name),
                    'aliases' => $this->manualAliasesForName(self::CATEGORY_ALIASES, $category->name),
                ])
                ->values()
                ->all();
        });
    }

    private function subcategorySnapshot(): array
    {
        return Cache::store('file')->remember('rfq_supplier_suggestion_subcategories_v2', now()->addMinutes(30), function (): array {
            return Subcategory::query()
                ->select(['subcategories.id', 'subcategories.name', 'subcategories.category_id', 'categories.name as category_name'])
                ->join('categories', 'categories.id', '=', 'subcategories.category_id')
                ->where('subcategories.is_active', true)
                ->where('categories.is_active', true)
                ->orderBy('subcategories.name')
                ->get()
                ->map(fn (Subcategory $subcategory) => [
                    'id' => (int) $subcategory->id,
                    'name' => $subcategory->name,
                    'category_id' => (int) $subcategory->category_id,
                    'category_name' => (string) $subcategory->category_name,
                    'normalized' => $this->normalize($subcategory->name),
                    'tokens' => $this->distinctiveTokens($subcategory->name),
                    'aliases' => $this->manualAliasesForName(self::SUBCATEGORY_ALIASES, $subcategory->name),
                ])
                ->values()
                ->all();
        });
    }

    private function isAiEnabled(): bool
    {
        return filled((string) config('services.openai.api_key'));
    }

    private function requestAiSuggestions(array $payload): ?array
    {
        try {
            $response = Http::baseUrl((string) config('services.openai.base_url'))
                ->withToken((string) config('services.openai.api_key'))
                ->timeout((int) config('services.openai.timeout', 60))
                ->acceptJson()
                ->post('/responses', [
                    'model' => (string) config('services.openai.rfq_import_model', 'gpt-4o-mini'),
                    'input' => [
                        ['role' => 'system', 'content' => $this->aiSuggestionSystemPrompt()],
                        ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'rfq_supplier_suggestions',
                            'strict' => true,
                            'schema' => $this->aiSuggestionSchema(),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('RFQ supplier suggestion AI request failed.', [
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
            Log::warning('RFQ supplier suggestion AI exception.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function aiSuggestionSystemPrompt(): string
    {
        return <<<'PROMPT'
You help classify maritime RFQ items into supplier filters.

Rules:
- Work row by row.
- Use only the candidate IDs provided for that row.
- You may use the document context to inherit a brand or a likely equipment family when the row is clearly part of the same spare-parts page.
- Prefer specific maritime spare-part reasoning over broad guesses.
- If evidence is weak, return empty arrays.
- Up to 3 brand IDs, up to 2 category IDs, up to 4 subcategory IDs per row.
- Confidence must be an integer between 0 and 100.
- The reason must be short, factual, and explain the strongest clue.
PROMPT;
    }

    private function aiSuggestionSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['rows'],
            'properties' => [
                'rows' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['row_index', 'brand_ids', 'category_ids', 'subcategory_ids', 'confidence', 'reason'],
                        'properties' => [
                            'row_index' => ['type' => 'integer'],
                            'brand_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer'],
                            ],
                            'category_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer'],
                            ],
                            'subcategory_ids' => [
                                'type' => 'array',
                                'items' => ['type' => 'integer'],
                            ],
                            'confidence' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 100,
                            ],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function brandAliasesForName(string $name): array
    {
        $normalized = $this->normalize($name);
        $aliases = [$normalized];

        foreach (self::BRAND_ALIASES as $target => $targetAliases) {
            if ($this->normalize($target) !== $normalized) {
                continue;
            }

            foreach ($targetAliases as $alias) {
                $aliases[] = $this->normalize($alias);
            }
        }

        $ascii = Str::of($name)->ascii()->lower()->toString();
        $aliases[] = $this->normalize(str_replace('&', ' and ', $ascii));
        $aliases[] = $this->normalize(str_replace('&', ' ', $ascii));
        $aliases[] = $this->normalize(str_replace(['-', '/'], ' ', $ascii));

        return array_values(array_unique(array_filter($aliases)));
    }

    private function manualAliasesForName(array $map, string $candidateName): array
    {
        $normalizedName = $this->normalize($candidateName);

        foreach ($map as $targetName => $aliases) {
            if ($this->normalize($targetName) !== $normalizedName) {
                continue;
            }

            return array_values(array_unique(array_filter(array_map(
                fn (string $alias) => $this->normalize($alias),
                $aliases
            ))));
        }

        return [];
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim();
    }

    private function compact(string $value): string
    {
        return str_replace(' ', '', $this->normalize($value));
    }

    private function tokenize(string $normalized): array
    {
        return array_values(array_filter(explode(' ', $normalized)));
    }

    private function distinctiveTokens(string $value): array
    {
        return collect($this->tokenize($this->normalize($value)))
            ->filter(fn (string $token) => mb_strlen($token) >= 4 && ! in_array($token, self::IGNORED_TOKENS, true))
            ->unique()
            ->values()
            ->all();
    }

    private function containsPhrase(string $haystack, string $needle): bool
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        return str_contains(" {$haystack} ", " {$needle} ");
    }

    private function confidenceFromScore(int $score): int
    {
        return match (true) {
            $score >= 520 => 99,
            $score >= 430 => 95,
            $score >= 360 => 90,
            $score >= 320 => 86,
            $score >= 260 => 82,
            $score >= 220 => 78,
            $score >= 180 => 70,
            $score >= 150 => 64,
            $score >= 120 => 58,
            default => 50,
        };
    }

    private function scoreFromConfidence(int $confidence): int
    {
        return match (true) {
            $confidence >= 96 => 520,
            $confidence >= 92 => 430,
            $confidence >= 86 => 320,
            $confidence >= 80 => 260,
            $confidence >= 72 => 220,
            $confidence >= 64 => 180,
            $confidence >= 56 => 150,
            default => 120,
        };
    }

    private function confidenceLabel(int $confidence): string
    {
        return match (true) {
            $confidence >= 85 => 'High',
            $confidence >= 65 => 'Medium',
            default => 'Low',
        };
    }

    private function makeEntry(array $entry): array
    {
        return $entry;
    }

    private function summaryText(array $brands, array $categories, array $subcategories): string
    {
        return sprintf(
            'We detected %d brand%s, %d categor%s, and %d subcategor%s from the item wording, marine aliases, surrounding request context, and low-confidence AI review.',
            count($brands),
            count($brands) === 1 ? '' : 's',
            count($categories),
            count($categories) === 1 ? 'y' : 'ies',
            count($subcategories),
            count($subcategories) === 1 ? 'y' : 'ies',
        );
    }
}
