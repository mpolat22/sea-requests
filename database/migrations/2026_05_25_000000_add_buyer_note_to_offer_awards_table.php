<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_awards', function (Blueprint $table) {
            $table->text('buyer_note')->nullable()->after('awarded_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('offer_awards', function (Blueprint $table) {
            $table->dropColumn('buyer_note');
        });
    }
};
