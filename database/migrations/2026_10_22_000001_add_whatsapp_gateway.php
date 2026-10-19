<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // إعدادات جهاز الربط (برنامج الواتساب على كمبيوتر المكتب) — سطر واحد
        if (!Schema::hasTable('wa_gateway_settings')) {
            Schema::create('wa_gateway_settings', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64);
                $table->unsignedSmallInteger('daily_limit')->default(50);
                $table->string('work_start', 5)->default('09:00');
                $table->string('work_end', 5)->default('21:00');
                $table->boolean('paused')->default(false);
                $table->timestamps();
            });
        }

        // الإرسال التلقائي للحملة
        Schema::table('wa_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_campaigns', 'auto_status')) {
                $table->string('auto_status', 10)->default('off');   // off | running | paused | done
            }
            if (!Schema::hasColumn('wa_campaigns', 'next_send_at')) {
                $table->timestamp('next_send_at')->nullable();
            }
        });

        Schema::table('wa_campaign_recipients', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_campaign_recipients', 'error')) {
                $table->string('error')->nullable();                  // سبب الفشل (الرقم ما عليه واتساب...)
            }
            if (!Schema::hasColumn('wa_campaign_recipients', 'via')) {
                $table->string('via', 10)->nullable();                // manual | auto
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_gateway_settings');
        Schema::table('wa_campaigns', function (Blueprint $table) {
            foreach (['auto_status', 'next_send_at'] as $c) if (Schema::hasColumn('wa_campaigns', $c)) $table->dropColumn($c);
        });
        Schema::table('wa_campaign_recipients', function (Blueprint $table) {
            foreach (['error', 'via'] as $c) if (Schema::hasColumn('wa_campaign_recipients', $c)) $table->dropColumn($c);
        });
    }
};
