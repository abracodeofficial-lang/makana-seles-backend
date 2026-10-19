<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // هل انعمل عقد وساطة مع المالك على هذا العقار
            if (!Schema::hasColumn('properties', 'has_brokerage_contract')) {
                $table->boolean('has_brokerage_contract')->default(false);
            }
            if (!Schema::hasColumn('properties', 'license_number')) {
                $table->string('license_number', 50)->nullable();   // رقم الترخيص (الإعلان)
            }
            if (!Schema::hasColumn('properties', 'plan_number')) {
                $table->string('plan_number', 50)->nullable();      // رقم المخطط
            }
            if (!Schema::hasColumn('properties', 'plot_number')) {
                $table->string('plot_number', 50)->nullable();      // رقم القطعة
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            foreach (['has_brokerage_contract', 'license_number', 'plan_number', 'plot_number'] as $col) {
                if (Schema::hasColumn('properties', $col)) $table->dropColumn($col);
            }
        });
    }
};
