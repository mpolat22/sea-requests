<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('seller_verification_onboarding_sent_at')
                ->nullable()
                ->after('seller_verification_submitted_at');
            $table->timestamp('seller_verification_24h_reminder_sent_at')
                ->nullable()
                ->after('seller_verification_onboarding_sent_at');
            $table->timestamp('seller_verification_72h_reminder_sent_at')
                ->nullable()
                ->after('seller_verification_24h_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'seller_verification_onboarding_sent_at',
                'seller_verification_24h_reminder_sent_at',
                'seller_verification_72h_reminder_sent_at',
            ]);
        });
    }
};
