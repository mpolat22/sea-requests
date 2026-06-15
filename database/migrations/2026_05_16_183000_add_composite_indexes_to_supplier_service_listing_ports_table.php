<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_service_listing_ports', function (Blueprint $table) {
            $table->index(['country_name', 'port_name'], 'ssl_ports_country_port_idx');
            $table->index(['country_code', 'port_name'], 'ssl_ports_code_port_idx');
            $table->index(['country_code', 'unlocode'], 'ssl_ports_code_unlocode_idx');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_service_listing_ports', function (Blueprint $table) {
            $table->dropIndex('ssl_ports_country_port_idx');
            $table->dropIndex('ssl_ports_code_port_idx');
            $table->dropIndex('ssl_ports_code_unlocode_idx');
        });
    }
};
