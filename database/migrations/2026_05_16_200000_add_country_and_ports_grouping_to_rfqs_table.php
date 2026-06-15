<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->json('country_names')->nullable()->after('port_name');
            $table->json('ports_by_country')->nullable()->after('country_names');
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn(['country_names', 'ports_by_country']);
        });
    }
};
