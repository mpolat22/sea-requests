<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_district')->nullable()->after('company_city');
            $table->string('company_neighborhood')->nullable()->after('company_district');
            $table->string('landline_phone')->nullable()->after('phone');
            $table->string('contact_email')->nullable()->after('email');
            $table->string('instagram_url')->nullable()->after('contact_email');
            $table->string('linkedin_url')->nullable()->after('instagram_url');
            $table->string('facebook_url')->nullable()->after('linkedin_url');
            $table->string('twitter_url')->nullable()->after('facebook_url');
            $table->string('telegram_url')->nullable()->after('twitter_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'company_district',
                'company_neighborhood',
                'landline_phone',
                'contact_email',
                'instagram_url',
                'linkedin_url',
                'facebook_url',
                'twitter_url',
                'telegram_url',
            ]);
        });
    }
};
