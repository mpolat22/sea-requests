<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ServiceRoute
{
    public static function vendorSlug(User $user): string
    {
        $base = Str::slug($user->company_name ?: $user->name);

        return ($base !== '' ? $base : 'supplier').'-'.$user->id;
    }

    public static function extractUserId(string $vendor): ?int
    {
        if (! preg_match('/-(\d+)$/', $vendor, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function params(User $user, ?Category $category, ?Subcategory $subcategory = null): array
    {
        $categorySlug = $category?->slug ?? 'service';

        return [
            'category' => $categorySlug,
            'subcategory' => $subcategory?->slug ?: $categorySlug,
            'vendor' => static::vendorSlug($user),
        ];
    }

    public static function url(User $user, ?Category $category, ?Subcategory $subcategory = null, string $routeName = 'services.show'): string
    {
        return route($routeName, static::params($user, $category, $subcategory));
    }

    public static function firstProfileUrl(User $user, array $query = [], string $routeName = 'services.show'): string
    {
        $categoryIds = collect($user->service_category_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        if ($categoryIds->isEmpty()) {
            return route('services.index', $query);
        }

        $category = Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->first(['id', 'name', 'slug']);

        if (! $category) {
            return route('services.index', $query);
        }

        $subcategoryIds = static::subcategoryIdsForCategory($user, $category->id);
        $subcategory = $subcategoryIds->isEmpty()
            ? null
            : Subcategory::query()
                ->where('category_id', $category->id)
                ->whereIn('id', $subcategoryIds)
                ->orderBy('name')
                ->first(['id', 'name', 'slug', 'category_id']);

        return route($routeName, array_merge(static::params($user, $category, $subcategory), $query));
    }

    protected static function subcategoryIdsForCategory(User $user, int $categoryId): Collection
    {
        $selectedByCategory = collect($user->service_subcategories_by_category ?? [])
            ->get((string) $categoryId, []);

        if (! empty($selectedByCategory)) {
            return collect($selectedByCategory)
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->values();
        }

        return collect($user->service_subcategory_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();
    }
}
