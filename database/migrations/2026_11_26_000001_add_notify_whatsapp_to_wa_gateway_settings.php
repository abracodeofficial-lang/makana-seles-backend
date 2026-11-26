<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// إشعارات النظام بتنبعت كمان على واتساب الموظف (عبر برنامج المكتب) — بتنطفي من إعدادات الربط
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('wa_gateway_settings', 'notify_whatsapp')) {
            Schema::table('wa_gateway_settings', function (Blueprint $table) {
                $table->boolean('notify_whatsapp')->default(true)->after('paused');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('wa_gateway_settings', 'notify_whatsapp')) {
            Schema::table('wa_gateway_settings', fn (Blueprint $table) => $table->dropColumn('notify_whatsapp'));
        }
    }
};
