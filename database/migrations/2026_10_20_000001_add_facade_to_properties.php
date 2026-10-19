<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // واجهة العقار — ممكن أكتر من وحدة (زاوية): "شمالية، شرقية"
        if (!Schema::hasColumn('properties', 'facade')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->string('facade', 100)->nullable()->after('direction');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('properties', 'facade')) {
            Schema::table('properties', fn (Blueprint $t) => $t->dropColumn('facade'));
        }
    }
};
