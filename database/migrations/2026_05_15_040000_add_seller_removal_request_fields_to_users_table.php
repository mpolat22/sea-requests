<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('seller_removal_request_reason')->nullable()->after('seller_verification_submitted_at');
            $table->string('seller_removal_request_status')->nullable()->after('seller_removal_request_reason');
            $table->timestamp('seller_removal_requested_at')->nullable()->after('seller_removal_request_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'seller_removal_request_reason',
                'seller_removal_request_status',
                'seller_removal_requested_at',
            ]);
        });
    }
};
