<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // الأعمدة التي كانت NOT NULL بدون default قابل للحل
            $table->foreignId('property_type_id')->nullable()->change();
            $table->decimal('listed_price', 15, 2)->nullable()->default(0)->change();
            $table->decimal('net_price',    15, 2)->nullable()->default(0)->change();
            $table->decimal('total_area',   10, 2)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('property_type_id')->nullable(false)->change();
            $table->decimal('listed_price', 15, 2)->nullable(false)->default(0)->change();
            $table->decimal('net_price',    15, 2)->nullable(false)->default(0)->change();
            $table->decimal('total_area',   10, 2)->nullable(false)->default(0)->change();
        });
    }
};
