<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('company_overview')->nullable()->after('company_description');
            $table->text('operating_regions')->nullable()->after('company_overview');
            $table->text('port_coverage')->nullable()->after('operating_regions');
            $table->string('company_logo_path')->nullable()->after('port_coverage');
            $table->string('company_cover_path')->nullable()->after('company_logo_path');
            $table->json('company_gallery')->nullable()->after('company_cover_path');
            $table->json('service_category_ids')->nullable()->after('company_gallery');
            $table->json('service_subcategory_ids')->nullable()->after('service_category_ids');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'company_overview',
                'operating_regions',
                'port_coverage',
                'company_logo_path',
                'company_cover_path',
                'company_gallery',
                'service_category_ids',
                'service_subcategory_ids',
            ]);
        });
    }
};
