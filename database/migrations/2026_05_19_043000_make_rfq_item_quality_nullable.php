<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_items', function (Blueprint $table) {
            $table->string('quality', 24)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rfq_items', function (Blueprint $table) {
            $table->string('quality', 24)->nullable(false)->change();
        });
    }
};
