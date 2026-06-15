<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Subcategory;

class ServiceDirectoryRoute
{
    public static function routeName(?Category $category = null, ?Subcategory $subcategory = null): string
    {
        if ($subcategory) {
            return 'services.subcategory';
        }

        if ($category) {
            return 'services.category';
        }

        return 'services.index';
    }

    public static function params(?Category $category = null, ?Subcategory $subcategory = null): array
    {
        if ($subcategory) {
            return [
                'category' => $category?->slug,
                'subcategory' => $subcategory->slug,
            ];
        }

        if ($category) {
            return [
                'category' => $category->slug,
            ];
        }

        return [];
    }

    public static function query(array $query = []): array
    {
        return collect($query)
            ->only(['search', 'country', 'port', 'page'])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->reject(function ($value) {
                if ($value === null) {
                    return true;
                }

                if (is_string($value) && $value === '') {
                    return true;
                }

                if ((is_int($value) || ctype_digit((string) $value)) && (int) $value <= 1) {
                    return true;
                }

                return false;
            })
            ->all();
    }

    public static function url(?Category $category = null, ?Subcategory $subcategory = null, array $query = []): string
    {
        $routeName = static::routeName($category, $subcategory);
        $url = route($routeName, static::params($category, $subcategory));
        $queryString = http_build_query(static::query($query));

        return $queryString !== '' ? "{$url}?{$queryString}" : $url;
    }
}
