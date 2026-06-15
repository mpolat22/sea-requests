<?php

use App\Support\SupplierServiceListingIndex;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SupplierServiceListingIndex::class)->rebuildAll();
    }

    public function down(): void
    {
        app(SupplierServiceListingIndex::class)->rebuildAll();
    }
};
