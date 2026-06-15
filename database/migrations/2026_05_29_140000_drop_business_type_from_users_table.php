<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'business_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('business_type');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'business_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('business_type')->nullable()->after('company_name');
            });
        }
    }
};
