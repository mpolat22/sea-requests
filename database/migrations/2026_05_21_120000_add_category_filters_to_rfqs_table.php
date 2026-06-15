<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->json('category_ids')->nullable()->after('ports_by_country');
            $table->json('subcategory_ids')->nullable()->after('category_ids');
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn(['category_ids', 'subcategory_ids']);
        });
    }
};
