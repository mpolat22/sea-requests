<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rfqs', 'imo_number')) {
            return;
        }

        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('imo_number', 7)->nullable()->after('ship_name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rfqs', 'imo_number')) {
            return;
        }

        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn('imo_number');
        });
    }
};
