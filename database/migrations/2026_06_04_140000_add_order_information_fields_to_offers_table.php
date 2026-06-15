<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('billing_company_name')->nullable()->after('order_workflow_status');
            $table->text('billing_address')->nullable()->after('billing_company_name');
            $table->string('billing_tax_id', 120)->nullable()->after('billing_address');
            $table->string('billing_contact_name', 120)->nullable()->after('billing_tax_id');
            $table->string('billing_contact_email')->nullable()->after('billing_contact_name');
            $table->string('billing_contact_phone', 60)->nullable()->after('billing_contact_email');

            $table->string('delivery_target_type', 40)->nullable()->after('billing_contact_phone');
            $table->string('delivery_country', 120)->nullable()->after('delivery_target_type');
            $table->string('delivery_port', 120)->nullable()->after('delivery_country');
            $table->text('delivery_address')->nullable()->after('delivery_port');
            $table->string('delivery_contact_name', 120)->nullable()->after('delivery_address');
            $table->string('delivery_contact_email')->nullable()->after('delivery_contact_name');
            $table->string('delivery_contact_phone', 60)->nullable()->after('delivery_contact_email');
            $table->date('delivery_required_date')->nullable()->after('delivery_contact_phone');

            $table->string('service_location_type', 40)->nullable()->after('delivery_required_date');
            $table->string('service_location')->nullable()->after('service_location_type');
            $table->string('service_contact_name', 120)->nullable()->after('service_location');
            $table->string('service_contact_email')->nullable()->after('service_contact_name');
            $table->string('service_contact_phone', 60)->nullable()->after('service_contact_email');
            $table->date('service_required_date')->nullable()->after('service_contact_phone');
            $table->text('service_instruction_notes')->nullable()->after('service_required_date');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'billing_company_name',
                'billing_address',
                'billing_tax_id',
                'billing_contact_name',
                'billing_contact_email',
                'billing_contact_phone',
                'delivery_target_type',
                'delivery_country',
                'delivery_port',
                'delivery_address',
                'delivery_contact_name',
                'delivery_contact_email',
                'delivery_contact_phone',
                'delivery_required_date',
                'service_location_type',
                'service_location',
                'service_contact_name',
                'service_contact_email',
                'service_contact_phone',
                'service_required_date',
                'service_instruction_notes',
            ]);
        });
    }
};
