<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('award_scope_policy', 40)
                ->default('partial_allowed')
                ->after('other_delivery_terms');
        });

        DB::table('offers')
            ->whereNull('award_scope_policy')
            ->update(['award_scope_policy' => 'partial_allowed']);
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('award_scope_policy');
        });
    }
};
