<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country')->nullable()->after('phone');
            $table->string('countries')->nullable()->after('country');
            $table->string('whatsapp_number')->nullable()->after('countries');
            $table->text('company_description')->nullable()->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'country',
                'countries',
                'whatsapp_number',
                'company_description',
            ]);
        });
    }
};
