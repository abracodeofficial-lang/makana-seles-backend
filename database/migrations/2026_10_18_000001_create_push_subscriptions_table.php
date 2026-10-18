<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // اشتراكات إشعارات الجهاز (Web Push) — كل متصفح/جهاز للموظف = سطر
        if (!Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('endpoint', 500)->unique();
                $table->string('p256dh');
                $table->string('auth');
                $table->string('user_agent')->nullable();
                $table->timestamps();
            });
        }

        // أيام العطلة الأسبوعية (أرقام Carbon: 0=الأحد ... 5=الجمعة، 6=السبت)
        // ما منبعت تذكير "دوامك رح يبدأ" بهالأيام
        if (Schema::hasTable('attendance_settings') && !Schema::hasColumn('attendance_settings', 'weekend_days')) {
            Schema::table('attendance_settings', function (Blueprint $table) {
                $table->json('weekend_days')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        if (Schema::hasColumn('attendance_settings', 'weekend_days')) {
            Schema::table('attendance_settings', fn (Blueprint $t) => $t->dropColumn('weekend_days'));
        }
    }
};
