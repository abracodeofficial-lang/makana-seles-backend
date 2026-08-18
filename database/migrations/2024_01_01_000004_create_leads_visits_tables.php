<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // جدول المهتمين
        // ============================================================
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_code')->unique(); // INT-2026-001
            $table->string('name');
            $table->string('phone');

            // --- التصنيف والمصدر ---
            $table->enum('applicant_type', ['مهتم', 'مشتري', 'مستأجر', 'وسيط', 'وكيل', 'مطور']);
            $table->enum('source', [
                'حراج', 'عقار', 'بيوت', 'ديل', 'موقع مكانة', 'مباشر', 'اتصال',
                'تيك توك', 'سناب', 'تويتر', 'انستجرام', 'برودكاست', 'لوحة',
                'يوتيوب', 'مجتمع', 'إعادة استهداف', 'سيتي سكيب', 'أخرى'
            ]);

            // --- تفاصيل الطلب ---
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('neighborhood_id')->nullable()->constrained('neighborhoods')->nullOnDelete();
            $table->decimal('offered_price', 15, 2)->default(0); // السعر المعروض
            $table->decimal('budget', 15, 2)->default(0); // ميزانية العميل
            $table->enum('price_category', ['أقل من 4M', 'بين 4-10M', 'أعلى من 10M'])->nullable();
            $table->boolean('is_available')->default(true); // حالة التوفر

            // --- التصنيف والجدية ---
            $table->enum('classification', ['جاد', 'استفسار', 'بحث'])->default('استفسار');
            $table->enum('seriousness_level', ['1', '2', '3', '4'])->default('1'); // نسبة الجدية
            $table->enum('purchase_goal', ['استثمار', 'سكن', 'سكن واستثمار'])->nullable();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();

            // --- الموظفون المسؤولون ---
            $table->foreignId('operation_employee_id')->nullable()->constrained('employees')->nullOnDelete(); // الابوريشن
            $table->foreignId('broker_employee_id')->nullable()->constrained('employees')->nullOnDelete(); // الوسيط
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete(); // المنشئ

            // --- حالة الطلب والمتابعة ---
            $table->enum('request_status', ['مفتوح', 'مغلق'])->default('مفتوح');
            $table->enum('update_status', [
                'تم البيع', 'استفسار', 'اهتمام أولي', 'اهتمام عالي', 'طلبات بحث'
            ])->default('استفسار');
            $table->date('update_date')->nullable();
            $table->text('update_notes')->nullable();
            $table->date('follow_up_date')->nullable(); // تاريخ المتابعة
            // حالة المتابعة تُحسب تلقائياً: قادم/اليوم/متأخر

            // --- الزيارة ---
            $table->date('visit_date')->nullable();
            $table->boolean('visit_coordinated')->default(false);
            $table->enum('visit_status', ['مجدولة', 'مؤكدة', 'تمت', 'ملغية'])->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================================
        // جدول الزيارات
        // ============================================================
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visit_code')->unique(); // V-2026-001
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('location')->nullable(); // اسم العقار أو الموقع
            $table->string('detailed_address')->nullable(); // العنوان التفصيلي
            $table->date('visit_date');
            $table->time('visit_time');
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', ['مجدولة', 'مؤكدة', 'تمت', 'ملغية'])->default('مجدولة');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
        Schema::dropIfExists('leads');
    }
};
