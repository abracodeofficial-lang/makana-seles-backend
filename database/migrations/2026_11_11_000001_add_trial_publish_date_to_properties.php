<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// تاريخ النشر التجريبي — نص حر بيكتبه الموظف (مش حقل تاريخ)
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('properties', 'trial_publish_date')) return;

        Schema::table('properties', function (Blueprint $table) {
            $table->string('trial_publish_date', 100)->nullable()->after('trial_property_code');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('properties', 'trial_publish_date')) return;

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('trial_publish_date');
        });
    }
};
