<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_address')->nullable()->after('company_description');
            $table->string('registration_number')->nullable()->after('company_address');
            $table->string('website_url')->nullable()->after('registration_number');
            $table->string('company_registration_document_path')->nullable()->after('website_url');
            $table->string('tax_certificate_document_path')->nullable()->after('company_registration_document_path');
            $table->string('service_authorization_document_path')->nullable()->after('tax_certificate_document_path');
            $table->timestamp('seller_verification_submitted_at')->nullable()->after('service_authorization_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'company_address',
                'registration_number',
                'website_url',
                'company_registration_document_path',
                'tax_certificate_document_path',
                'service_authorization_document_path',
                'seller_verification_submitted_at',
            ]);
        });
    }
};
