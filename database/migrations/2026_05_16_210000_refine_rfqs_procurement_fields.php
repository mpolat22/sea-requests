<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->renameColumn('delivery_date', 'requisition_date');
            $table->renameColumn('urgency', 'priority');
        });

        Schema::table('rfq_items', function (Blueprint $table) {
            $table->renameColumn('brand', 'manufacturer');
            $table->renameColumn('description', 'comments');
            $table->string('model_type')->nullable()->after('manufacturer');
            $table->string('serial_number')->nullable()->after('model_type');
            $table->string('catalog_code')->nullable()->after('serial_number');
            $table->decimal('rob', 14, 2)->nullable()->after('catalog_code');
            $table->string('drawing_number')->nullable()->after('rob');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_items', function (Blueprint $table) {
            $table->dropColumn([
                'model_type',
                'serial_number',
                'catalog_code',
                'rob',
                'drawing_number',
            ]);
            $table->renameColumn('manufacturer', 'brand');
            $table->renameColumn('comments', 'description');
        });

        Schema::table('rfqs', function (Blueprint $table) {
            $table->renameColumn('requisition_date', 'delivery_date');
            $table->renameColumn('priority', 'urgency');
        });
    }
};
