<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->string('unlocode', 16)->unique();
            $table->string('country_code', 2)->index();
            $table->string('location_code', 8)->nullable()->index();
            $table->string('country_name', 120)->nullable()->index();
            $table->string('port_name', 160)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ports');
    }
};
