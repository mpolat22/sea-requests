<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_address_line')->nullable()->after('company_address');
            $table->string('company_city')->nullable()->after('company_address_line');
            $table->string('company_state')->nullable()->after('company_city');
            $table->string('company_postal_code')->nullable()->after('company_state');
            $table->string('company_location_name')->nullable()->after('company_postal_code');
            $table->decimal('company_latitude', 10, 7)->nullable()->after('company_location_name');
            $table->decimal('company_longitude', 10, 7)->nullable()->after('company_latitude');
            $table->json('company_registration_documents')->nullable()->after('company_longitude');
            $table->json('tax_certificate_documents')->nullable()->after('company_registration_documents');
            $table->json('service_authorization_documents')->nullable()->after('tax_certificate_documents');
        });

        DB::table('users')
            ->whereNotNull('company_registration_document_path')
            ->orWhereNotNull('tax_certificate_document_path')
            ->orWhereNotNull('service_authorization_document_path')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'company_registration_documents' => $user->company_registration_document_path
                            ? json_encode([['path' => $user->company_registration_document_path]])
                            : null,
                        'tax_certificate_documents' => $user->tax_certificate_document_path
                            ? json_encode([['path' => $user->tax_certificate_document_path]])
                            : null,
                        'service_authorization_documents' => $user->service_authorization_document_path
                            ? json_encode([['path' => $user->service_authorization_document_path]])
                            : null,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'company_address_line',
                'company_city',
                'company_state',
                'company_postal_code',
                'company_location_name',
                'company_latitude',
                'company_longitude',
                'company_registration_documents',
                'tax_certificate_documents',
                'service_authorization_documents',
            ]);
        });
    }
};
