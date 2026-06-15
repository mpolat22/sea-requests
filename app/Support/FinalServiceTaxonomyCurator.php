<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Rfq;
use App\Models\ShipservCategoryImport;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinalServiceTaxonomyCurator
{
    public function curate(bool $activate = true): array
    {
        return DB::transaction(function () use ($activate) {
            $roots = collect(config('final_service_taxonomy.roots', []))->values();

            $canonicalIdMap = [];
            $aliasMap = [];
            $categoryRemaps = [];
            $subcategoryRemaps = [];
            $mergedCategories = 0;
            $movedSubcategories = 0;
            $deduplicatedSubcategories = 0;

            foreach ($roots as $index => $root) {
                $canonical = $this->resolveCanonicalCategory($root, $index, $activate);
                $canonicalIdMap[$canonical->id] = $canonical;

                $aliases = collect([$root['name'] ?? null, $root['primary'] ?? null])
                    ->merge($root['aliases'] ?? [])
                    ->filter()
                    ->unique()
                    ->values();

                foreach ($aliases as $aliasName) {
                    $aliasMap[$this->normalizeCategoryAliasName((string) $aliasName)] = $canonical->id;

                    $aliasCategories = Category::query()
                        ->where('name', $aliasName)
                        ->orderBy('id')
                        ->get();

                    foreach ($aliasCategories as $aliasCategory) {
                        if ($aliasCategory->id === $canonical->id) {
                            continue;
                        }

                        $summary = $this->mergeCategoryInto($aliasCategory, $canonical, $activate);
                        $mergedCategories += $summary['merged'] ? 1 : 0;
                        $movedSubcategories += $summary['moved_subcategories'];
                        $deduplicatedSubcategories += count($summary['subcategory_remaps']);

                        if ($summary['merged']) {
                            $categoryRemaps[$aliasCategory->id] = $canonical->id;
                        }

                        foreach ($summary['subcategory_remaps'] as $fromId => $toId) {
                            $subcategoryRemaps[(int) $fromId] = (int) $toId;
                        }
                    }
                }
            }

            $residualAliasSummary = $this->mergeResidualAliasCategories($aliasMap, $canonicalIdMap, $activate);
            $mergedCategories += $residualAliasSummary['merged_categories'];
            $movedSubcategories += $residualAliasSummary['moved_subcategories'];
            $deduplicatedSubcategories += $residualAliasSummary['deduplicated_subcategories'];
            $categoryRemaps += $residualAliasSummary['category_remaps'];
            $subcategoryRemaps += $residualAliasSummary['subcategory_remaps'];

            $canonicalIds = array_keys($canonicalIdMap);
            $canonicalCategories = Category::query()
                ->whereIn('id', $canonicalIds)
                ->get()
                ->keyBy('name')
                ->all();

            $exactOverrideSummary = $this->applySubcategoryExactOverrides($canonicalCategories, $activate);
            $movedSubcategories += $exactOverrideSummary['moved_subcategories'];
            $deduplicatedSubcategories += $exactOverrideSummary['deduplicated_subcategories'];
            $subcategoryRemaps += $exactOverrideSummary['subcategory_remaps'];

            $patternOverrideSummary = $this->applySubcategoryPatternOverrides($canonicalCategories, $activate);
            $movedSubcategories += $patternOverrideSummary['moved_subcategories'];
            $deduplicatedSubcategories += $patternOverrideSummary['deduplicated_subcategories'];
            $subcategoryRemaps += $patternOverrideSummary['subcategory_remaps'];

            if ($activate) {
                Category::query()->whereIn('id', $canonicalIds)->update([
                    'is_active' => true,
                    'has_subcategories' => true,
                ]);

                Subcategory::query()->whereIn('category_id', $canonicalIds)->update([
                    'is_active' => true,
                ]);
            }

            $deactivatedSubcategories = $this->applySubcategoryDeactivations();

            $emptyCategoriesDeactivated = $this->deactivateEmptyCanonicalCategories($canonicalIds);

            Category::query()->whereNotIn('id', $canonicalIds)->get()->each(function (Category $category) {
                $metadata = $category->metadata ?? [];
                $metadata['taxonomy_visibility'] = 'inactive_alias';

                $category->forceFill([
                    'is_active' => false,
                    'has_subcategories' => false,
                    'metadata' => $metadata,
                ])->save();
            });

            $nonCanonicalSubcategoriesDeactivated = Subcategory::query()
                ->whereNotIn('category_id', $canonicalIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $inactiveCategorySubcategoriesDeactivated = $this->deactivateActiveSubcategoriesOnInactiveCategories();

            return [
                'final_categories' => count($canonicalIds),
                'merged_categories' => $mergedCategories,
                'moved_subcategories' => $movedSubcategories,
                'deduplicated_subcategories' => $deduplicatedSubcategories,
                'category_remaps' => count($categoryRemaps),
                'subcategory_remaps' => count($subcategoryRemaps),
                'exact_override_moves' => $exactOverrideSummary['moved_subcategories'],
                'pattern_override_moves' => $patternOverrideSummary['moved_subcategories'],
                'deactivated_subcategories' => $deactivatedSubcategories,
                'empty_categories_deactivated' => $emptyCategoriesDeactivated,
                'noncanonical_subcategories_deactivated' => $nonCanonicalSubcategoriesDeactivated,
                'inactive_category_subcategories_deactivated' => $inactiveCategorySubcategoriesDeactivated,
                'activated' => $activate ? 'yes' : 'no',
                'active_categories' => Category::query()->where('is_active', true)->count(),
                'active_subcategories' => Subcategory::query()->where('is_active', true)->count(),
            ];
        });
    }

    private function resolveCanonicalCategory(array $root, int $index, bool $activate): Category
    {
        $preferredName = (string) ($root['primary'] ?? $root['name']);
        $finalName = (string) $root['name'];
        $finalSlug = (string) $root['slug'];

        $category = Category::query()->where('name', $finalName)->first();

        if (! $category) {
            $category = Category::query()->where('name', $preferredName)->first();
        }

        if (! $category) {
            $category = Category::query()->where('slug', $finalSlug)->first();
        }

        if (! $category) {
            $category = new Category();
        }

        $metadata = $category->metadata ?? [];
        $metadata['taxonomy_root'] = true;
        $metadata['taxonomy_aliases'] = array_values(array_unique(array_filter($root['aliases'] ?? [])));
        $metadata['taxonomy_primary'] = $preferredName;

        $category->fill([
            'name' => $finalName,
            'slug' => $finalSlug,
            'has_subcategories' => true,
            'is_active' => $activate,
            'sort_order' => ($index + 1) * 10,
            'source' => 'curated',
            'source_external_id' => null,
            'source_url' => null,
            'metadata' => $metadata,
        ])->save();

        return $category->fresh();
    }

    private function mergeResidualAliasCategories(array $aliasMap, array $canonicalIdMap, bool $activate): array
    {
        $categoryRemaps = [];
        $subcategoryRemaps = [];
        $mergedCategories = 0;
        $movedSubcategories = 0;
        $deduplicatedSubcategories = 0;

        $canonicalIds = array_keys($canonicalIdMap);

        $categories = Category::query()
            ->whereNotIn('id', $canonicalIds)
            ->orderBy('id')
            ->get();

        foreach ($categories as $category) {
            $targetId = $aliasMap[$this->normalizeCategoryAliasName($category->name)] ?? null;

            if (! $targetId) {
                $metadataTargetId = (int) ($category->metadata['alias_of_category_id'] ?? 0);
                if ($metadataTargetId !== 0 && isset($canonicalIdMap[$metadataTargetId])) {
                    $targetId = $metadataTargetId;
                }
            }

            if (! $targetId || ! isset($canonicalIdMap[$targetId]) || $targetId === $category->id) {
                continue;
            }

            $summary = $this->mergeCategoryInto($category, $canonicalIdMap[$targetId], $activate);
            $mergedCategories += $summary['merged'] ? 1 : 0;
            $movedSubcategories += $summary['moved_subcategories'];
            $deduplicatedSubcategories += count($summary['subcategory_remaps']);

            if ($summary['merged']) {
                $categoryRemaps[$category->id] = $targetId;
            }

            foreach ($summary['subcategory_remaps'] as $fromId => $toId) {
                $subcategoryRemaps[(int) $fromId] = (int) $toId;
            }
        }

        return [
            'merged_categories' => $mergedCategories,
            'moved_subcategories' => $movedSubcategories,
            'deduplicated_subcategories' => $deduplicatedSubcategories,
            'category_remaps' => $categoryRemaps,
            'subcategory_remaps' => $subcategoryRemaps,
        ];
    }

    private function applySubcategoryExactOverrides(array $canonicalCategories, bool $activate): array
    {
        $subcategoryRemaps = [];
        $movedSubcategories = 0;
        $deduplicatedSubcategories = 0;

        foreach (config('final_service_taxonomy.subcategory_overrides', []) as $override) {
            $targetCategory = $canonicalCategories[$override['category'] ?? ''] ?? null;

            if (! $targetCategory) {
                continue;
            }

            $matches = Subcategory::query()
                ->where('name', $override['name'] ?? '')
                ->orderBy('id')
                ->get();

            foreach ($matches as $subcategory) {
                $result = $this->moveSubcategoryToCategory($subcategory, $targetCategory, $activate, 'exact_override');
                $movedSubcategories += $result['moved_subcategories'];
                $deduplicatedSubcategories += $result['deduplicated_subcategories'];
                $subcategoryRemaps += $result['subcategory_remaps'];
            }
        }

        return [
            'moved_subcategories' => $movedSubcategories,
            'deduplicated_subcategories' => $deduplicatedSubcategories,
            'subcategory_remaps' => $subcategoryRemaps,
        ];
    }

    private function applySubcategoryPatternOverrides(array $canonicalCategories, bool $activate): array
    {
        $subcategoryRemaps = [];
        $movedSubcategories = 0;
        $deduplicatedSubcategories = 0;
        $exactOverrideNames = collect(config('final_service_taxonomy.subcategory_overrides', []))
            ->pluck('name')
            ->filter()
            ->map(fn ($name) => Str::lower((string) $name))
            ->values()
            ->all();

        foreach (config('final_service_taxonomy.pattern_overrides', []) as $rule) {
            $targetCategory = $canonicalCategories[$rule['category'] ?? ''] ?? null;

            if (! $targetCategory) {
                continue;
            }

            $fromCategories = collect($rule['from'] ?? [])->filter()->values();
            $matches = collect($rule['match'] ?? [])->filter()->values()->all();

            $query = Subcategory::query()
                ->with('category:id,name')
                ->where('is_active', true);

            if ($fromCategories->isNotEmpty()) {
                $query->whereHas('category', function ($builder) use ($fromCategories) {
                    $builder->whereIn('name', $fromCategories->all());
                });
            }

            foreach ($query->orderBy('id')->get() as $subcategory) {
                if (
                    ! $subcategory->category
                    || in_array(Str::lower($subcategory->name), $exactOverrideNames, true)
                    || ! $this->matchesAnyPattern($subcategory->name, $matches)
                ) {
                    continue;
                }

                $result = $this->moveSubcategoryToCategory($subcategory, $targetCategory, $activate, 'pattern_override');
                $movedSubcategories += $result['moved_subcategories'];
                $deduplicatedSubcategories += $result['deduplicated_subcategories'];
                $subcategoryRemaps += $result['subcategory_remaps'];
            }
        }

        return [
            'moved_subcategories' => $movedSubcategories,
            'deduplicated_subcategories' => $deduplicatedSubcategories,
            'subcategory_remaps' => $subcategoryRemaps,
        ];
    }

    private function applySubcategoryDeactivations(): int
    {
        $deactivated = 0;

        foreach (config('final_service_taxonomy.deactivate_exact_subcategories', []) as $name) {
            $matches = Subcategory::query()
                ->where('name', $name)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            foreach ($matches as $subcategory) {
                $deactivated += $this->deactivateSubcategory($subcategory, 'exact_deactivate');
            }
        }

        foreach (config('final_service_taxonomy.deactivate_pattern_rules', []) as $rule) {
            $fromCategories = collect($rule['from'] ?? [])->filter()->values();
            $matches = collect($rule['match'] ?? [])->filter()->values()->all();

            $query = Subcategory::query()
                ->with('category:id,name')
                ->where('is_active', true);

            if ($fromCategories->isNotEmpty()) {
                $query->whereHas('category', function ($builder) use ($fromCategories) {
                    $builder->whereIn('name', $fromCategories->all());
                });
            }

            foreach ($query->orderBy('id')->get() as $subcategory) {
                if (! $subcategory->category || ! $this->matchesAnyPattern($subcategory->name, $matches)) {
                    continue;
                }

                $deactivated += $this->deactivateSubcategory($subcategory, 'pattern_deactivate');
            }
        }

        return $deactivated;
    }

    private function mergeCategoryInto(Category $from, Category $to, bool $activate): array
    {
        $subcategoryRemaps = [];
        $movedSubcategories = 0;

        foreach (Subcategory::query()->where('category_id', $from->id)->orderBy('id')->get() as $subcategory) {
            $result = $this->moveSubcategoryToCategory($subcategory, $to, $activate, 'category_merge');
            $movedSubcategories += $result['moved_subcategories'];
            $subcategoryRemaps += $result['subcategory_remaps'];
        }

        $this->remapCategoryReferences($from->id, $to->id);

        $metadata = $from->metadata ?? [];
        $metadata['alias_of_category_id'] = $to->id;
        $metadata['taxonomy_visibility'] = 'inactive_alias';

        $from->forceFill([
            'is_active' => false,
            'has_subcategories' => false,
            'metadata' => $metadata,
        ])->save();

        return [
            'merged' => true,
            'moved_subcategories' => $movedSubcategories,
            'subcategory_remaps' => $subcategoryRemaps,
        ];
    }

    private function moveSubcategoryToCategory(Subcategory $subcategory, Category $targetCategory, bool $activate, string $reason): array
    {
        if ($subcategory->category_id === $targetCategory->id) {
            if ($activate && ! $subcategory->is_active) {
                $subcategory->forceFill(['is_active' => true])->save();
            }

            return [
                'moved_subcategories' => 0,
                'deduplicated_subcategories' => 0,
                'subcategory_remaps' => [],
            ];
        }

        $fromCategoryId = (int) $subcategory->category_id;

        $duplicate = Subcategory::query()
            ->where('category_id', $targetCategory->id)
            ->where('id', '!=', $subcategory->id)
            ->where(function ($query) use ($subcategory) {
                $query->where('name', $subcategory->name)
                    ->orWhere('slug', $subcategory->slug);
            })
            ->first();

        if ($duplicate) {
            $this->remapSubcategoryReferences($subcategory->id, $duplicate->id, $fromCategoryId, $targetCategory->id);

            $metadata = $subcategory->metadata ?? [];
            $metadata['merged_into_subcategory_id'] = $duplicate->id;
            $metadata['merged_into_category_id'] = $targetCategory->id;
            $metadata['curation_reason'] = $reason;

            $subcategory->forceFill([
                'is_active' => false,
                'metadata' => $metadata,
            ])->save();

            return [
                'moved_subcategories' => 0,
                'deduplicated_subcategories' => 1,
                'subcategory_remaps' => [$subcategory->id => $duplicate->id],
            ];
        }

        $metadata = $subcategory->metadata ?? [];
        $metadata['curation_reason'] = $reason;
        $metadata['moved_from_category_id'] = $fromCategoryId;
        $metadata['moved_to_category_id'] = $targetCategory->id;

        $subcategory->forceFill([
            'category_id' => $targetCategory->id,
            'is_active' => $activate,
            'metadata' => $metadata,
        ])->save();

        $this->reassignSubcategoryCategoryReferences($subcategory->id, $fromCategoryId, $targetCategory->id);

        return [
            'moved_subcategories' => 1,
            'deduplicated_subcategories' => 0,
            'subcategory_remaps' => [],
        ];
    }

    private function deactivateSubcategory(Subcategory $subcategory, string $reason): int
    {
        if (! $subcategory->is_active) {
            return 0;
        }

        $categoryId = (int) $subcategory->category_id;
        $subcategoryId = (int) $subcategory->id;

        User::query()->get()->each(function (User $user) use ($subcategoryId, $categoryId) {
            $subcategoryIds = array_values(array_filter(
                array_map('intval', $user->service_subcategory_ids ?? []),
                fn ($id) => $id !== $subcategoryId
            ));

            $byCategory = collect($user->service_subcategories_by_category ?? []);
            $key = (string) $categoryId;
            $values = array_map('intval', $byCategory->get($key, $byCategory->get($categoryId, [])));
            $values = array_values(array_filter($values, fn ($id) => $id !== $subcategoryId));

            $byCategory->forget($key);
            $byCategory->forget($categoryId);
            if (! empty($values)) {
                $byCategory->put($key, $values);
            }

            $user->forceFill([
                'service_subcategory_ids' => $subcategoryIds,
                'service_subcategories_by_category' => $byCategory->toArray(),
            ])->save();
        });

        SupplierServiceListing::query()
            ->where('subcategory_id', $subcategoryId)
            ->update([
                'subcategory_id' => null,
                'subcategory_name' => null,
                'subcategory_slug' => null,
            ]);

        Rfq::query()->get()->each(function (Rfq $rfq) use ($subcategoryId) {
            $subcategoryIds = array_map('intval', $rfq->subcategory_ids ?? []);

            if (! in_array($subcategoryId, $subcategoryIds, true)) {
                return;
            }

            $rfq->forceFill([
                'subcategory_ids' => array_values(array_filter($subcategoryIds, fn ($id) => $id !== $subcategoryId)),
            ])->save();
        });

        $metadata = $subcategory->metadata ?? [];
        $metadata['taxonomy_visibility'] = 'curated_hidden';
        $metadata['curation_reason'] = $reason;

        $subcategory->forceFill([
            'is_active' => false,
            'metadata' => $metadata,
        ])->save();

        return 1;
    }

    private function remapCategoryReferences(int $fromCategoryId, int $toCategoryId): void
    {
        User::query()->get()->each(function (User $user) use ($fromCategoryId, $toCategoryId) {
            $categoryIds = array_values(array_unique(array_map(
                fn ($id) => (int) $id === $fromCategoryId ? $toCategoryId : (int) $id,
                array_map('intval', $user->service_category_ids ?? [])
            )));

            $byCategory = collect($user->service_subcategories_by_category ?? []);
            $fromKey = (string) $fromCategoryId;
            $toKey = (string) $toCategoryId;

            if ($byCategory->has($fromKey) || $byCategory->has($fromCategoryId)) {
                $fromValues = $byCategory->get($fromKey, $byCategory->get($fromCategoryId, []));
                $toValues = $byCategory->get($toKey, $byCategory->get($toCategoryId, []));
                $mergedValues = array_values(array_unique(array_map('intval', array_merge($toValues, $fromValues))));

                $byCategory->forget($fromKey);
                $byCategory->forget($fromCategoryId);
                $byCategory->put($toKey, $mergedValues);
            }

            $user->forceFill([
                'service_category_ids' => $categoryIds,
                'service_subcategories_by_category' => $byCategory->toArray(),
            ])->save();
        });

        SupplierServiceListing::query()
            ->where('category_id', $fromCategoryId)
            ->update(['category_id' => $toCategoryId]);

        ShipservCategoryImport::query()
            ->where('category_id', $fromCategoryId)
            ->update(['category_id' => $toCategoryId]);

        Rfq::query()->get()->each(function (Rfq $rfq) use ($fromCategoryId, $toCategoryId) {
            $categoryIds = array_values(array_unique(array_map(
                fn ($id) => (int) $id === $fromCategoryId ? $toCategoryId : (int) $id,
                array_map('intval', $rfq->category_ids ?? [])
            )));

            $rfq->forceFill([
                'category_ids' => $categoryIds,
            ])->save();
        });
    }

    private function remapSubcategoryReferences(int $fromSubcategoryId, int $toSubcategoryId, int $fromCategoryId, int $toCategoryId): void
    {
        User::query()->get()->each(function (User $user) use ($fromSubcategoryId, $toSubcategoryId, $fromCategoryId, $toCategoryId) {
            $subcategoryIds = array_values(array_unique(array_map(
                fn ($id) => (int) $id === $fromSubcategoryId ? $toSubcategoryId : (int) $id,
                array_map('intval', $user->service_subcategory_ids ?? [])
            )));

            $byCategory = collect($user->service_subcategories_by_category ?? []);
            $fromKey = (string) $fromCategoryId;
            $toKey = (string) $toCategoryId;
            $fromValues = array_map('intval', $byCategory->get($fromKey, $byCategory->get($fromCategoryId, [])));
            $toValues = array_map('intval', $byCategory->get($toKey, $byCategory->get($toCategoryId, [])));

            $fromValues = array_values(array_filter(array_map(
                fn ($id) => $id === $fromSubcategoryId ? $toSubcategoryId : $id,
                $fromValues
            )));

            $mergedValues = array_values(array_unique(array_merge($toValues, $fromValues)));

            $byCategory->forget($fromKey);
            $byCategory->forget($fromCategoryId);
            $byCategory->put($toKey, $mergedValues);

            $user->forceFill([
                'service_subcategory_ids' => $subcategoryIds,
                'service_subcategories_by_category' => $byCategory->toArray(),
            ])->save();
        });

        SupplierServiceListing::query()
            ->where('subcategory_id', $fromSubcategoryId)
            ->update([
                'category_id' => $toCategoryId,
                'subcategory_id' => $toSubcategoryId,
            ]);

        ShipservCategoryImport::query()
            ->where('subcategory_id', $fromSubcategoryId)
            ->update([
                'category_id' => $toCategoryId,
                'subcategory_id' => $toSubcategoryId,
            ]);

        Rfq::query()->get()->each(function (Rfq $rfq) use ($fromSubcategoryId, $toSubcategoryId, $toCategoryId) {
            $subcategoryIds = array_map('intval', $rfq->subcategory_ids ?? []);

            if (! in_array($fromSubcategoryId, $subcategoryIds, true)) {
                return;
            }

            $subcategoryIds = array_values(array_unique(array_map(
                fn ($id) => (int) $id === $fromSubcategoryId ? $toSubcategoryId : (int) $id,
                $subcategoryIds
            )));

            $categoryIds = array_map('intval', $rfq->category_ids ?? []);
            if (! in_array($toCategoryId, $categoryIds, true)) {
                $categoryIds[] = $toCategoryId;
            }

            $rfq->forceFill([
                'category_ids' => array_values(array_unique($categoryIds)),
                'subcategory_ids' => $subcategoryIds,
            ])->save();
        });
    }

    private function reassignSubcategoryCategoryReferences(int $subcategoryId, int $fromCategoryId, int $toCategoryId): void
    {
        User::query()->get()->each(function (User $user) use ($subcategoryId, $fromCategoryId, $toCategoryId) {
            $categoryIds = array_map('intval', $user->service_category_ids ?? []);
            if (! in_array($toCategoryId, $categoryIds, true)) {
                $categoryIds[] = $toCategoryId;
            }

            $byCategory = collect($user->service_subcategories_by_category ?? []);
            $fromKey = (string) $fromCategoryId;
            $toKey = (string) $toCategoryId;
            $fromValues = array_map('intval', $byCategory->get($fromKey, $byCategory->get($fromCategoryId, [])));
            $toValues = array_map('intval', $byCategory->get($toKey, $byCategory->get($toCategoryId, [])));

            if (in_array($subcategoryId, $fromValues, true)) {
                $fromValues = array_values(array_filter($fromValues, fn ($id) => $id !== $subcategoryId));
            }

            if (! in_array($subcategoryId, $toValues, true)) {
                $toValues[] = $subcategoryId;
            }

            $byCategory->forget($fromKey);
            $byCategory->forget($fromCategoryId);
            if (! empty($fromValues)) {
                $byCategory->put($fromKey, array_values(array_unique($fromValues)));
            }

            $byCategory->forget($toKey);
            $byCategory->forget($toCategoryId);
            $byCategory->put($toKey, array_values(array_unique($toValues)));

            $user->forceFill([
                'service_category_ids' => array_values(array_unique($categoryIds)),
                'service_subcategories_by_category' => $byCategory->toArray(),
            ])->save();
        });

        SupplierServiceListing::query()
            ->where('subcategory_id', $subcategoryId)
            ->update(['category_id' => $toCategoryId]);

        ShipservCategoryImport::query()
            ->where('subcategory_id', $subcategoryId)
            ->update(['category_id' => $toCategoryId]);

        Rfq::query()->get()->each(function (Rfq $rfq) use ($subcategoryId, $toCategoryId) {
            $subcategoryIds = array_map('intval', $rfq->subcategory_ids ?? []);

            if (! in_array($subcategoryId, $subcategoryIds, true)) {
                return;
            }

            $categoryIds = array_map('intval', $rfq->category_ids ?? []);
            if (! in_array($toCategoryId, $categoryIds, true)) {
                $categoryIds[] = $toCategoryId;
            }

            $rfq->forceFill([
                'category_ids' => array_values(array_unique($categoryIds)),
            ])->save();
        });
    }

    private function deactivateEmptyCanonicalCategories(array $canonicalIds): int
    {
        $deactivated = 0;

        $categories = Category::query()
            ->whereIn('id', $canonicalIds)
            ->withCount(['subcategories' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get();

        foreach ($categories as $category) {
            if ($category->subcategories_count > 0) {
                continue;
            }

            $metadata = $category->metadata ?? [];
            $metadata['taxonomy_visibility'] = 'inactive_empty_root';

            $category->forceFill([
                'is_active' => false,
                'has_subcategories' => false,
                'metadata' => $metadata,
            ])->save();

            $deactivated++;
        }

        return $deactivated;
    }

    private function deactivateActiveSubcategoriesOnInactiveCategories(): int
    {
        $deactivated = 0;

        Subcategory::query()
            ->with('category:id,name,is_active')
            ->where('is_active', true)
            ->whereHas('category', function ($query) {
                $query->where('is_active', false);
            })
            ->get()
            ->each(function (Subcategory $subcategory) use (&$deactivated) {
                $metadata = $subcategory->metadata ?? [];
                $metadata['taxonomy_visibility'] = 'inactive_category_cleanup';

                $subcategory->forceFill([
                    'is_active' => false,
                    'metadata' => $metadata,
                ])->save();

                $deactivated++;
            });

        return $deactivated;
    }

    private function normalizeCategoryAliasName(string $name): string
    {
        return Str::lower(trim($name));
    }

    private function matchesAnyPattern(string $name, array $patterns): bool
    {
        $normalized = Str::lower($name);

        foreach ($patterns as $pattern) {
            if (Str::contains($normalized, Str::lower((string) $pattern))) {
                return true;
            }
        }

        return false;
    }
}
