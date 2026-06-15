<?php

namespace App\Support;

use App\Models\Category;
use App\Models\ShipservCategoryImport;
use App\Models\Subcategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipServCategoryCleanupEngine
{
    public function prepare(?int $limit = null): array
    {
        $families = collect(config('shipserv_taxonomy.families', []));
        $serviceTerms = collect(config('shipserv_taxonomy.service_terms', []))
            ->map(fn (string $term) => $this->normalize($term))
            ->filter()
            ->values();

        $duplicateMap = ShipservCategoryImport::query()
            ->select('normalized_slug', DB::raw('count(*) as total'))
            ->whereNotNull('normalized_slug')
            ->groupBy('normalized_slug')
            ->pluck('total', 'normalized_slug');

        $query = ShipservCategoryImport::query()->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $prepared = 0;
        $summary = [
            'category' => 0,
            'subcategory' => 0,
            'review' => 0,
            'ignore' => 0,
        ];

        $query->chunkById(250, function (Collection $imports) use ($families, $serviceTerms, $duplicateMap, &$prepared, &$summary) {
            foreach ($imports as $import) {
                $normalizedName = $this->normalize($import->name);
                $normalizedSlug = Str::slug($normalizedName);
                $duplicateCount = (int) ($duplicateMap[$normalizedSlug] ?? 0);

                $analysis = $this->analyseImport(
                    import: $import,
                    normalizedName: $normalizedName,
                    normalizedSlug: $normalizedSlug,
                    families: $families,
                    serviceTerms: $serviceTerms,
                    duplicateCount: $duplicateCount,
                );

                $import->forceFill([
                    'normalized_name' => $analysis['normalized_name'],
                    'normalized_slug' => $analysis['normalized_slug'],
                    'content_type' => $analysis['content_type'],
                    'suggestion_type' => $analysis['suggestion_type'],
                    'suggested_parent_name' => $analysis['suggested_parent_name'],
                    'suggested_parent_slug' => $analysis['suggested_parent_slug'],
                    'suggested_parent_source' => $analysis['suggested_parent_source'],
                    'suggestion_confidence' => $analysis['suggestion_confidence'],
                    'suggestion_rule' => $analysis['suggestion_rule'],
                    'mapping_notes' => $analysis['mapping_notes'],
                    'mapping_status' => $analysis['mapping_status'],
                    'publish_status' => $analysis['publish_status'],
                ])->save();

                $prepared++;
                $summary[$analysis['suggestion_type']] = ($summary[$analysis['suggestion_type']] ?? 0) + 1;
            }
        });

        return [
            'prepared' => $prepared,
            'category_suggestions' => $summary['category'] ?? 0,
            'subcategory_suggestions' => $summary['subcategory'] ?? 0,
            'review_suggestions' => $summary['review'] ?? 0,
            'ignored' => $summary['ignore'] ?? 0,
        ];
    }

    public function publish(int $minConfidence = 60, bool $activate = false): array
    {
        $query = ShipservCategoryImport::query()
            ->whereIn('suggestion_type', ['category', 'subcategory'])
            ->where('suggestion_confidence', '>=', $minConfidence)
            ->orderBy('id');

        $publishedCategoryIds = [];
        $publishedSubcategoryIds = [];
        $importsConsidered = 0;

        $query->chunkById(250, function (Collection $imports) use ($activate, &$publishedCategoryIds, &$publishedSubcategoryIds, &$importsConsidered) {
            foreach ($imports as $import) {
                $importsConsidered++;

                if ($import->suggestion_type === 'category') {
                    $category = $this->publishCategory($import, $activate);
                    $this->saveImportPublishState($import, [
                        'category_id' => $category->id,
                        'publish_status' => $activate ? 'published_active' : 'published_inactive',
                        'mapping_status' => 'published',
                    ]);

                    $publishedCategoryIds[$category->id] = true;
                    continue;
                }

                $parent = $this->resolveOrCreateParentCategory($import, $activate);
                $subcategory = $this->publishSubcategory($import, $parent, $activate);

                $this->saveImportPublishState($import, [
                    'category_id' => $parent->id,
                    'subcategory_id' => $subcategory->id,
                    'publish_status' => $activate ? 'published_active' : 'published_inactive',
                    'mapping_status' => 'published',
                ]);

                $publishedCategoryIds[$parent->id] = true;
                $publishedSubcategoryIds[$subcategory->id] = true;
            }
        });

        return [
            'imports_considered' => $importsConsidered,
            'categories_published' => count($publishedCategoryIds),
            'subcategories_published' => count($publishedSubcategoryIds),
            'activated' => $activate ? 'yes' : 'no',
            'min_confidence' => $minConfidence,
        ];
    }

    private function analyseImport(
        ShipservCategoryImport $import,
        string $normalizedName,
        string $normalizedSlug,
        Collection $families,
        Collection $serviceTerms,
        int $duplicateCount,
    ): array {
        $contentType = $this->detectContentType($normalizedName, $serviceTerms);

        if ($normalizedName === '' || $normalizedSlug === '') {
            return $this->result($normalizedName, $normalizedSlug, $contentType, 'ignore', null, null, null, 0, 'empty_name', 'Ignored because the normalized name is empty.', 'ignored', 'ignored');
        }

        if ($this->isReferencePublication($import->name, $normalizedName)) {
            return $this->result($normalizedName, $normalizedSlug, $contentType, 'ignore', null, null, null, 0, 'reference_publication', 'Ignored because this entry looks like a regulation, code, convention, guideline, or publication reference rather than a supplier matching taxonomy node.', 'ignored', 'ignored');
        }

        $override = $this->overrideSuggestion($normalizedName, $contentType);

        if ($override !== null) {
            return $this->result(
                $normalizedName,
                $normalizedSlug,
                $contentType,
                'subcategory',
                $override['name'],
                $override['slug'],
                $override['source'],
                92,
                'name_override',
                'Matched a curated high-priority override before generic family keyword analysis.',
                'prepared',
                'prepared',
            );
        }

        if ($import->is_featured) {
            return $this->result($normalizedName, $normalizedSlug, $contentType, 'category', null, null, 'shipserv_featured', 100, 'featured_anchor', 'Featured ShipServ category suggested as a parent category.', 'prepared', 'prepared');
        }

        foreach ($families as $family) {
            $familyName = (string) ($family['name'] ?? '');
            $familySlug = (string) ($family['slug'] ?? '');

            if ($this->normalize($familyName) === $normalizedName) {
                return $this->result($normalizedName, $normalizedSlug, $contentType, 'category', null, null, (string) ($family['source'] ?? 'generated'), 95, 'exact_family_name', 'Exact family name match; suggested as a standalone category.', 'prepared', 'prepared');
            }
        }

        foreach ($families as $family) {
            if ($this->matchesFamily($normalizedName, $family)) {
                return $this->result(
                    $normalizedName,
                    $normalizedSlug,
                    $contentType,
                    'subcategory',
                    (string) $family['name'],
                    (string) $family['slug'],
                    (string) ($family['source'] ?? 'generated'),
                    85,
                    'keyword_family_match',
                    $duplicateCount > 1
                        ? 'Matched to a configured family keyword set. Duplicate normalized name exists in multiple ShipServ rows; this import will resolve to the same published node.'
                        : 'Matched to a configured family keyword set.',
                    'prepared',
                    'prepared',
                );
            }
        }

        if ($contentType === 'service') {
            return $this->result($normalizedName, $normalizedSlug, $contentType, 'subcategory', 'Services', 'services', 'shipserv', 55, 'default_service_bucket', 'Service-like entry without a specific family match; sent to Services catch-all.', 'prepared', 'prepared');
        }

        if ($contentType === 'product') {
            return $this->result($normalizedName, $normalizedSlug, $contentType, 'subcategory', 'Marine Supply', 'marine-supply', 'existing', 55, 'default_product_bucket', 'Product-like entry without a specific family match; sent to Marine Supply catch-all.', 'prepared', 'prepared');
        }

        return $this->result($normalizedName, $normalizedSlug, $contentType, 'review', null, null, null, 20, 'unclassified', 'Could not classify this entry with enough confidence.', 'prepared', 'review');
    }

    private function publishCategory(ShipservCategoryImport $import, bool $activate): Category
    {
        $sourceHint = $import->suggested_parent_source ?: ($import->is_featured ? 'shipserv' : null);
        $slug = $import->normalized_slug ?: $import->slug;
        $existingCategory = Category::query()->where('slug', $slug)->first();

        if ($existingCategory && $existingCategory->source !== 'shipserv') {
            $metadata = $existingCategory->metadata ?? [];
            $metadata['shipserv_match'] = [
                'external_id' => $import->shipserv_external_id,
                'source_url' => $import->source_url,
                'suggestion_rule' => $import->suggestion_rule,
                'matched_at' => now()->toIso8601String(),
            ];

            $existingCategory->fill([
                'has_subcategories' => true,
                'is_active' => $activate ? true : (bool) $existingCategory->is_active,
                'metadata' => $metadata,
            ])->save();

            return $existingCategory;
        }

        $category = Category::query()
            ->where('source', 'shipserv')
            ->where('source_external_id', $import->shipserv_external_id)
            ->first();

        if (! $category) {
            $category = $existingCategory ?: Category::query()->firstOrNew([
                'slug' => $slug,
            ]);
        }

        $category->fill([
            'name' => $import->name,
            'slug' => $slug,
            'has_subcategories' => true,
            'is_active' => $activate ? true : ($category->exists ? (bool) $category->is_active : false),
            'source' => $sourceHint === 'shipserv' || $sourceHint === 'shipserv_featured' ? 'shipserv' : $category->source,
            'source_external_id' => $sourceHint === 'shipserv' || $sourceHint === 'shipserv_featured'
                ? $import->shipserv_external_id
                : $category->source_external_id,
            'source_url' => $sourceHint === 'shipserv' || $sourceHint === 'shipserv_featured'
                ? $import->source_url
                : $category->source_url,
            'metadata' => [
                'letter' => $import->letter,
                'content_type' => $import->content_type,
                'suggestion_rule' => $import->suggestion_rule,
                'is_featured' => $import->is_featured,
            ],
        ])->save();

        return $category;
    }

    private function resolveOrCreateParentCategory(ShipservCategoryImport $import, bool $activate): Category
    {
        $parentSlug = $import->suggested_parent_slug ?: Str::slug((string) $import->suggested_parent_name);
        $parentName = $import->suggested_parent_name ?: $import->name;
        $parentSource = $import->suggested_parent_source ?: 'generated';

        $category = Category::query()->where('slug', $parentSlug)->first();

        if (! $category && $parentSource === 'shipserv') {
            $categoryImport = ShipservCategoryImport::query()
                ->where('normalized_slug', $parentSlug)
                ->orWhere('slug', $parentSlug)
                ->first();

            if ($categoryImport) {
                return $this->publishCategory($categoryImport, $activate);
            }
        }

        if (! $category) {
            $category = Category::query()->create([
                'name' => $parentName,
                'slug' => $parentSlug,
                'has_subcategories' => true,
                'is_active' => $activate,
                'source' => $parentSource === 'shipserv' ? 'shipserv' : null,
                'source_url' => $parentSource === 'shipserv' ? $import->source_url : null,
                'metadata' => [
                    'created_from' => 'shipserv_cleanup_parent',
                    'parent_source' => $parentSource,
                ],
            ]);
        } elseif ($activate && ! $category->is_active) {
            $category->forceFill(['is_active' => true])->save();
        }

        return $category;
    }

    private function publishSubcategory(ShipservCategoryImport $import, Category $parent, bool $activate): Subcategory
    {
        $subcategory = Subcategory::query()
            ->where('source', 'shipserv')
            ->where('source_external_id', $import->shipserv_external_id)
            ->first();

        $slug = Str::slug($parent->name.' '.$import->name);
        $nameConflict = Subcategory::query()
            ->where('category_id', $parent->id)
            ->where('name', $import->name)
            ->when($subcategory, fn ($query) => $query->where('id', '!=', $subcategory->id))
            ->first();

        if ($nameConflict) {
            if ($subcategory && $subcategory->id !== $nameConflict->id) {
                $metadata = $subcategory->metadata ?? [];
                $metadata['merged_into_subcategory_id'] = $nameConflict->id;

                $subcategory->forceFill([
                    'is_active' => false,
                    'metadata' => $metadata,
                ])->save();
            }

            $nameConflict->forceFill([
                'is_active' => $activate ? true : (bool) $nameConflict->is_active,
            ])->save();

            return $nameConflict;
        }

        $slugConflict = Subcategory::query()
            ->where('slug', $slug)
            ->when($subcategory, fn ($query) => $query->where('id', '!=', $subcategory->id))
            ->first();

        if ($slugConflict) {
            if ($subcategory && $subcategory->id !== $slugConflict->id) {
                $metadata = $subcategory->metadata ?? [];
                $metadata['merged_into_subcategory_id'] = $slugConflict->id;

                $subcategory->forceFill([
                    'is_active' => false,
                    'metadata' => $metadata,
                ])->save();
            }

            $slugConflict->forceFill([
                'category_id' => $parent->id,
                'is_active' => $activate ? true : (bool) $slugConflict->is_active,
            ])->save();

            return $slugConflict;
        }

        if (! $subcategory) {
            $subcategory = Subcategory::query()->firstOrNew([
                'slug' => $slug,
            ]);
        }

        $subcategory->fill([
            'category_id' => $parent->id,
            'name' => $import->name,
            'slug' => $slug,
            'is_active' => $activate ? true : ($subcategory->exists ? (bool) $subcategory->is_active : false),
            'source' => 'shipserv',
            'source_external_id' => $import->shipserv_external_id,
            'source_url' => $import->source_url,
            'metadata' => [
                'letter' => $import->letter,
                'content_type' => $import->content_type,
                'suggestion_rule' => $import->suggestion_rule,
                'suggested_parent' => $import->suggested_parent_name,
            ],
        ])->save();

        return $subcategory;
    }

    private function matchesFamily(string $normalizedName, array $family): bool
    {
        foreach (($family['keywords'] ?? []) as $keyword) {
            $needle = $this->normalize((string) $keyword);

            if ($needle !== '' && preg_match('/(^| )'.preg_quote($needle, '/').'( |$)/', $normalizedName) === 1) {
                return true;
            }
        }

        return false;
    }

    private function detectContentType(string $normalizedName, Collection $serviceTerms): string
    {
        foreach ($serviceTerms as $term) {
            if ($term !== '' && str_contains($normalizedName, $term)) {
                return 'service';
            }
        }

        return 'product';
    }

    private function isReferencePublication(string $rawName, string $normalizedName): bool
    {
        if ($normalizedName === '') {
            return false;
        }

        if (preg_match('/\b(code|convention|guidelines|guideline|edition|session|res|resolution|amendment|annex)\b/i', $rawName) !== 1) {
            return false;
        }

        return preg_match('/\b(imo|modu|ftp|hns|esp|tdc)\b/i', $rawName) === 1
            || preg_match('/\b(19|20)\d{2}\b/', $rawName) === 1;
    }

    private function overrideSuggestion(string $normalizedName, string $contentType): ?array
    {
        $overrides = [
            [
                'name' => 'Medical & Hospital',
                'slug' => 'medical-hospital',
                'source' => 'curated',
                'needles' => ['anatomical chart', 'airway', 'cholesterol', 'glucose', 'audiometry', 'antidote', 'blood pressure', 'wound care', 'aed', 'cervical collar', 'ankle support', 'arm sling', 'medical oxygen', 'aeromedical'],
            ],
            [
                'name' => 'Safety & PPE',
                'slug' => 'safety-ppe',
                'source' => 'curated',
                'needles' => ['crew overboard marker light', 'survivor locator light', 'thermal protection', 'ring buoy', 'marker light', 'pyrotechnic', 'arc flash', 'life jacket', 'lifejacket', 'immersion suit'],
            ],
            [
                'name' => 'Accommodation, Galley & Laundry',
                'slug' => 'accommodation-galley-laundry',
                'source' => 'curated',
                'needles' => ['captain s chair', 'beach chair', 'beach chairs', 'bar stool', 'bar stools', 'desk chair', 'desk chairs', 'bunk bed', 'bed sheet', 'bed spread', 'mattress', 'mattresses', 'bath robe', 'bath mat', 'robe', 'rugs', 'furniture'],
            ],
            [
                'name' => 'Navigation & Communication Systems',
                'slug' => 'navigation-communication-systems',
                'source' => 'curated',
                'needles' => ['autopilot', 'autopilots', 'nautical almanac', 'list of lights', 'tide tables', 'star finder', 'azimuth', 'chart plotter', 'chart updating', 'bridge equipment', 'compass', 'navtex', 'radar'],
            ],
            [
                'name' => 'Anchors, Chains & Fenders',
                'slug' => 'anchors-chains-fenders',
                'source' => 'curated',
                'needles' => ['fenders and buoys'],
            ],
            [
                'name' => 'Crew & Manpower',
                'slug' => 'crew-manpower',
                'source' => 'curated',
                'needles' => ['crew boat', 'crew boats', 'crew supply boat', 'crew supply boats', 'crew travel', 'riding crew', 'port captain', 'port captains', 'supercargo'],
            ],
            [
                'name' => 'Marine Technology & Digital',
                'slug' => 'marine-technology-digital',
                'source' => 'curated',
                'needles' => ['software', 'cctv', 'networking', 'computer', 'application', 'applications', 'anti virus', 'simulator'],
            ],
            [
                'name' => 'Offshore & Specialized',
                'slug' => 'offshore-specialized',
                'source' => 'curated',
                'needles' => ['support vessel', 'support vessels', 'tender vessel', 'tender vessels', 'dive support vessel', 'dive support vessels'],
            ],
            [
                'name' => 'Fasteners, Hardware & Clamps',
                'slug' => 'fasteners-hardware-clamps',
                'source' => 'curated',
                'needles' => ['bolt', 'bolts', 'nut', 'nuts', 'screw', 'screws', 'washer', 'washers', 'hinge', 'hinges', 'bracket', 'brackets', 'pin', 'pins', 'stud', 'studs', 'latch', 'hook', 'hooks', 'bar clamp'],
            ],
            [
                'name' => 'Marine Chemicals, Gases & Treatment',
                'slug' => 'marine-chemicals-gases-treatment',
                'source' => 'curated',
                'needles' => ['acetylene', 'argon', 'shielding gas', 'anti freeze', 'antifreeze', 'additive', 'additives', 'biocide', 'bactericide', 'antifoulant', 'solvent', 'thinner', 'welding gas'],
            ],
            [
                'name' => 'General Marine Services',
                'slug' => 'general-marine-services',
                'source' => 'curated',
                'needles' => ['car rental services', 'classification services', 'cleaning services', 'fumigation services', 'authorized manufacturer service', 'diesel engine service'],
            ],
        ];

        foreach ($overrides as $override) {
            foreach ($override['needles'] as $needle) {
                $normalizedNeedle = $this->normalize($needle);

                if ($normalizedNeedle !== '' && preg_match('/(^| )'.preg_quote($normalizedNeedle, '/').'( |$)/', $normalizedName) === 1) {
                    return $override;
                }
            }
        }

        return $contentType === 'service' && preg_match('/(^| )(support vessel|support vessels|tender vessel|tender vessels)( |$)/', $normalizedName) === 1
            ? ['name' => 'Offshore & Specialized', 'slug' => 'offshore-specialized', 'source' => 'curated']
            : null;
    }

    private function normalize(string $value): string
    {
        $value = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();

        return trim($value);
    }

    private function saveImportPublishState(ShipservCategoryImport $import, array $attributes): void
    {
        $attempts = 0;

        beginning:
        try {
            $import->forceFill($attributes)->save();
        } catch (QueryException $exception) {
            $attempts++;

            if ($attempts < 4 && str_contains((string) $exception->getMessage(), '1213 Deadlock found')) {
                usleep(150000 * $attempts);
                goto beginning;
            }

            throw $exception;
        }
    }

    private function result(
        string $normalizedName,
        string $normalizedSlug,
        string $contentType,
        string $suggestionType,
        ?string $parentName,
        ?string $parentSlug,
        ?string $parentSource,
        int $confidence,
        string $rule,
        string $notes,
        string $mappingStatus,
        string $publishStatus,
    ): array {
        return [
            'normalized_name' => $normalizedName,
            'normalized_slug' => $normalizedSlug,
            'content_type' => $contentType,
            'suggestion_type' => $suggestionType,
            'suggested_parent_name' => $parentName,
            'suggested_parent_slug' => $parentSlug,
            'suggested_parent_source' => $parentSource,
            'suggestion_confidence' => $confidence,
            'suggestion_rule' => $rule,
            'mapping_notes' => $notes,
            'mapping_status' => $mappingStatus,
            'publish_status' => $publishStatus,
        ];
    }
}
