<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// كود عقار تجريبي يكتبه الموظف — منفصل عن property_code التلقائي
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('properties', 'trial_property_code')) return;

        Schema::table('properties', function (Blueprint $table) {
            $table->string('trial_property_code', 50)->nullable()->after('property_code');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('properties', 'trial_property_code')) return;

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('trial_property_code');
        });
    }
};
