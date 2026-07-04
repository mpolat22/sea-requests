<?php

use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use App\Support\SupplierServiceListingIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;

return new class extends Migration
{
    public function up(): void
    {
        $targetSubcategoryIds = Subcategory::query()
            ->whereIn('name', ['Food Processors', 'Food Provision'])
            ->pluck('id');

        $sellerIds = collect();

        if ($targetSubcategoryIds->isNotEmpty()) {
            foreach ($targetSubcategoryIds as $subcategoryId) {
                $sellerIds = $sellerIds->merge(
                    User::query()
                        ->where('role', 'seller')
                        ->whereJsonContains('service_subcategory_ids', (int) $subcategoryId)
                        ->pluck('id')
                );
            }
        }

        $sellerIds = $sellerIds
            ->merge(
                SupplierServiceListing::query()
                    ->where(function ($query) {
                        $query->whereIn('subcategory_name', ['Food Processors', 'Food Provision'])
                            ->orWhereIn('subcategory_slug', [
                                'food-processors',
                                'food-provision',
                                'provisions-food-processors',
                                'provisions-food-provision',
                            ]);
                    })
                    ->pluck('seller_id')
            )
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $this->syncSellers($sellerIds);
    }

    public function down(): void
    {
        // No-op: this repair migration only rebuilds derived supplier listing snapshots.
    }

    private function syncSellers(Collection $sellerIds): void
    {
        if ($sellerIds->isEmpty()) {
            return;
        }

        $index = app(SupplierServiceListingIndex::class);

        User::query()
            ->whereIn('id', $sellerIds)
            ->where('role', 'seller')
            ->get()
            ->each(fn (User $seller) => $index->syncSeller($seller, flushPublicCaches: false));
    }
};
