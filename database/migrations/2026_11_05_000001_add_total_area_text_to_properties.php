<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// المساحة كنص حر زي ما انكتبت — total_area بيضل الرقم المستخدم لسعر المتر والنشر
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('properties', 'total_area_text')) return;

        Schema::table('properties', function (Blueprint $table) {
            $table->string('total_area_text', 50)->nullable()->after('total_area');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('properties', 'total_area_text')) return;

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('total_area_text');
        });
    }
};
