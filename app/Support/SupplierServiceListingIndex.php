<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Port;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierServiceListingIndex
{
    private function normalizeSearchText(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]+/', ' ')
            ->squish()
            ->toString();
    }

    public function syncSeller(User $seller, bool $flushPublicCaches = true): void
    {
        if (! $seller->isSeller() || ! $seller->isApproved()) {
            $this->clearSeller($seller, $flushPublicCaches);

            return;
        }

        $categoryIds = collect($seller->service_category_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();
        $subcategoryIds = collect($seller->service_subcategories_by_category ?? [])
            ->flatten(1)
            ->merge($seller->service_subcategory_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            $this->clearSeller($seller, $flushPublicCaches);

            return;
        }

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        $subcategories = Subcategory::query()
            ->whereIn('id', $subcategoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category_id']);
        $brandIds = collect($seller->service_brand_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();
        $brands = Brand::query()
            ->whereIn('id', $brandIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        $ports = $seller->servicePorts()
            ->orderBy('country_name')
            ->orderBy('port_name')
            ->get(['ports.id', 'ports.country_code', 'ports.country_name', 'ports.port_name', 'ports.unlocode']);

        if ($categories->isEmpty()) {
            $this->clearSeller($seller, $flushPublicCaches);

            return;
        }

        DB::transaction(function () use ($seller, $categories, $subcategories, $brands, $ports) {
            $existingIdsByKey = SupplierServiceListing::query()
                ->where('seller_id', $seller->id)
                ->pluck('id', 'listing_key');

            $listings = $this->buildListingRows($seller, $categories, $subcategories, $brands, $ports);
            $nextKeys = collect($listings)->pluck('listing_key')->all();

            SupplierServiceListing::query()
                ->where('seller_id', $seller->id)
                ->whereNotIn('listing_key', $nextKeys)
                ->delete();

            foreach ($listings as $listingData) {
                $listing = SupplierServiceListing::query()->updateOrCreate(
                    ['listing_key' => $listingData['listing_key']],
                    $listingData
                );

                $listing->ports()->delete();

                if ($ports->isNotEmpty()) {
                    $listing->ports()->createMany($this->buildPortRows($ports));
                }
            }
        });

        if ($flushPublicCaches) {
            $this->forgetPublicCaches();
        }
    }

    public function clearSeller(User $seller, bool $flushPublicCaches = true): void
    {
        SupplierServiceListing::query()
            ->where('seller_id', $seller->id)
            ->delete();

        if ($flushPublicCaches) {
            $this->forgetPublicCaches();
        }
    }

    public function rebuildAll(): void
    {
        User::query()
            ->where('role', 'seller')
            ->orderBy('id')
            ->chunkById(100, function ($sellers) {
                foreach ($sellers as $seller) {
                    $this->syncSeller($seller, false);
                }
            });

        $this->forgetPublicCaches();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildListingRows(
        User $seller,
        Collection $categories,
        Collection $subcategories,
        Collection $brands,
        Collection $ports
    ): array
    {
        $companyName = $seller->company_name ?: $seller->name;
        $country = CountryNameResolver::resolve((string) $seller->country);
        $summary = $seller->company_overview ?: $seller->company_description;
        $vendorSlug = ServiceRoute::vendorSlug($seller);

        $rows = [];

        foreach ($categories as $category) {
            $groupedSubcategoryIds = collect($seller->service_subcategories_by_category ?? [])
                ->get((string) $category->id, []);

            $resolvedSubcategoryIds = collect($groupedSubcategoryIds)
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->values();

            if ($resolvedSubcategoryIds->isEmpty()) {
                $resolvedSubcategoryIds = $subcategories
                    ->filter(fn (Subcategory $subcategory) => (int) $subcategory->category_id === (int) $category->id)
                    ->pluck('id')
                    ->map(fn ($value) => (int) $value)
                    ->values();
            }

            $matchingSubcategories = $subcategories
                ->filter(fn (Subcategory $subcategory) => (int) $subcategory->category_id === (int) $category->id)
                ->filter(fn (Subcategory $subcategory) => $resolvedSubcategoryIds->contains((int) $subcategory->id))
                ->values();

            if ($matchingSubcategories->isEmpty()) {
                $rows[] = $this->makeListingRow(
                    $seller,
                    $companyName,
                    $country,
                    $summary,
                    $vendorSlug,
                    $category,
                    null,
                    $brands,
                    $ports
                );

                continue;
            }

            foreach ($matchingSubcategories as $subcategory) {
                $rows[] = $this->makeListingRow(
                    $seller,
                    $companyName,
                    $country,
                    $summary,
                    $vendorSlug,
                    $category,
                    $subcategory,
                    $brands,
                    $ports
                );
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeListingRow(
        User $seller,
        string $companyName,
        ?string $country,
        ?string $summary,
        string $vendorSlug,
        Category $category,
        ?Subcategory $subcategory,
        Collection $brands,
        Collection $ports
    ): array {
        $listingKey = implode(':', [
            $seller->id,
            $category->id,
            $subcategory?->id ?: 0,
        ]);

        $searchText = collect([
            $companyName,
            $seller->name,
            $country,
            $summary,
            $category->name,
            $category->slug,
            $subcategory?->name,
            $subcategory?->slug,
            $brands->pluck('name')->all(),
            $brands->pluck('slug')->all(),
            $ports->flatMap(fn (Port $port) => [
                CountryNameResolver::resolve((string) ($port->country_code ?: $port->country_name)) ?? $port->country_name,
                $port->country_code,
                $port->port_name,
                $port->unlocode,
            ])->all(),
        ])
            ->flatten()
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->implode(' ');

        return [
            'seller_id' => $seller->id,
            'listing_key' => $listingKey,
            'company_name' => $companyName,
            'contact_name' => $seller->name,
            'country' => $country,
            'summary' => $summary,
            'logo_path' => $seller->company_logo_path,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'category_slug' => $category->slug,
            'subcategory_id' => $subcategory?->id,
            'subcategory_name' => $subcategory?->name,
            'subcategory_slug' => $subcategory?->slug,
            'vendor_slug' => $vendorSlug,
            'search_text' => $this->normalizeSearchText($searchText),
            'is_visible' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPortRows(Collection $ports): array
    {
        return $ports
            ->map(fn (Port $port) => [
                'country_code' => $port->country_code,
                'country_name' => CountryNameResolver::resolve((string) ($port->country_code ?: $port->country_name)) ?? $port->country_name,
                'port_name' => $port->port_name,
                'unlocode' => $port->unlocode,
            ])
            ->all();
    }

    private function forgetPublicCaches(): void
    {
        Cache::forget('home:featured-suppliers:v2:8');
        Cache::forget('home:featured-suppliers:v3:8');
        Cache::forget('home:hero-stats:v1');
        Cache::forget('home:hero-stats:v2');
        Cache::forget('home:hero-stats:v3');
    }
}
