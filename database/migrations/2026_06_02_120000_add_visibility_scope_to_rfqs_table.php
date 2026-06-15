<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('visibility_scope', 32)
                ->default('public_marketplace')
                ->after('request_type')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropIndex(['visibility_scope']);
            $table->dropColumn('visibility_scope');
        });
    }
};
