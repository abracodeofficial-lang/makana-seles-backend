<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // جدول المدن
        // ============================================================
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique(); // RN, RE, RW, RS, RC, TB
            $table->timestamps();
        });

        // ============================================================
        // جدول الأحياء
        // ============================================================
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->timestamps();
        });

        // ============================================================
        // جدول أنواع العقارات
        // ============================================================
        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // شقة، فلة، عمارة، أرض، أدوار، بلك
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        // جدول أنواع التسويق
        // ============================================================
        Schema::create('marketing_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ملاك أفراد، مزادات، مشاريع على الخارطة، مشاريع جاهزة
            $table->string('ad_number')->nullable(); // رقم الإعلان
            $table->integer('count')->default(0); // العدد
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        // جدول أنواع الاستخدام
        // ============================================================
        Schema::create('usage_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // سكني، تجاري، سكني تجاري، مستودعات، زراعي...
            $table->text('details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        // جدول طرق الدفع
        // ============================================================
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // بنك، كاش، بنك وكاش
            $table->string('code', 10)->unique(); // BANK, CASH, BOTH
            $table->timestamps();
        });

        // ============================================================
        // جدول الأقسام (HR)
        // ============================================================
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // المبيعات، التسويق، المالية، الموارد البشرية، تقنية المعلومات، الإدارة
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        // جدول أنواع التعاقد
        // ============================================================
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // دوام كامل، جزئي، مؤقت، محدد المدة، عمل حر، تدريب، تطوع
            $table->text('details')->nullable();
            $table->timestamps();
        });

        // ============================================================
        // جدول الشفتات
        // ============================================================
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // صباحي، مسائي، ليلي، مرن
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(15); // فترة السماح بالتأخير
            $table->timestamps();
        });

        // ============================================================
        // جدول أنواع الإجازات
        // ============================================================
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // سنوية، مرضية، اعتيادية، عارضة، بدون أجر
            $table->unsignedSmallInteger('total_days'); // الرصيد الإجمالي لكل إجازة
            $table->boolean('requires_attachment')->default(false); // تحتاج مرفق؟
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ============================================================
        // جدول إعدادات الدوام
        // ============================================================
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(15);
            $table->decimal('overtime_multiplier', 3, 1)->default(1.5); // معامل حساب الساعة الإضافية
            $table->unsignedSmallInteger('deduction_after_late_days')->default(3); // بعد كم يوم تأخير يبدأ الخصم
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('contract_types');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('usage_types');
        Schema::dropIfExists('marketing_types');
        Schema::dropIfExists('property_types');
        Schema::dropIfExists('neighborhoods');
        Schema::dropIfExists('cities');
    }
};
