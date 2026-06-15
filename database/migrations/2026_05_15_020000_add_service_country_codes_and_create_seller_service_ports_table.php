<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('service_country_codes')->nullable()->after('port_coverage');
        });

        Schema::create('seller_service_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('port_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'port_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_service_ports');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('service_country_codes');
        });
    }
};
