<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // جدول الملاك
        // ============================================================
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('owner_code')->unique(); // OW-2026-001
            $table->string('name'); // اسم المالك أو الشركة
            $table->enum('type', ['مالك', 'وكيل', 'وسيط', 'مكتب', 'مطور', 'مشروع']);
            $table->string('phone');
            $table->string('email')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('address')->nullable();

            // --- الاتفاقيات ---
            $table->enum('exclusive_status', ['تم', 'لا', 'جاري', 'ملغي'])->default('لا'); // حصري
            $table->enum('brokerage_status', ['تم', 'لا', 'جاري', 'ملغي'])->default('لا'); // وساطة
            $table->enum('auction_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد'); // مزاد
            $table->date('price_update_date')->nullable(); // تاريخ تحديث السعر
            $table->date('agreement_end_date')->nullable(); // تاريخ انتهاء الاتفاق
            $table->enum('group', ['A', 'B', 'C'])->nullable(); // المجموعة

            // --- التجهيز الفني ---
            $table->enum('photo_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد');
            $table->enum('design_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد');
            $table->enum('video_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد');
            $table->enum('publishing_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد');

            // --- الموظفون المسؤولون ---
            $table->foreignId('sales_employee_owners_id')->nullable()->constrained('employees')->nullOnDelete(); // موظف مبيعات الملاك
            $table->foreignId('sales_employee_leads_id')->nullable()->constrained('employees')->nullOnDelete();  // موظف مبيعات المهتمين
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete(); // المنشئ

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================================
        // جدول العقارات
        // ============================================================
        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            // --- المعلومات الأساسية ---
            $table->string('property_code')->unique(); // RE-2026-001
            $table->string('name'); // اسم العقار
            $table->foreignId('property_type_id')->constrained('property_types');
            $table->foreignId('marketing_type_id')->nullable()->constrained('marketing_types')->nullOnDelete();
            $table->foreignId('usage_type_id')->nullable()->constrained('usage_types')->nullOnDelete();
            $table->enum('status', ['متاح', 'محجوز', 'مباع', 'قيد المراجعة'])->default('متاح');
            $table->boolean('is_verified')->default(false); // محقق
            $table->string('source')->nullable(); // المصدر

            // --- التسعير ---
            $table->decimal('listed_price', 15, 2)->default(0); // السعر المعروض
            $table->decimal('net_price', 15, 2)->default(0); // السعر الصافي
            $table->decimal('price_per_meter', 10, 2)->default(0); // سعر المتر (يُحسب)
            $table->decimal('market_price_3months', 15, 2)->default(0); // سعر السوق آخر 3 شهور
            $table->string('market_price_note')->nullable(); // ملاحظة سعر السوق
            $table->decimal('discount_amount', 15, 2)->default(0); // مبلغ الخصم

            // --- المساحة ---
            $table->decimal('total_area', 10, 2)->default(0); // م²
            $table->decimal('land_area', 10, 2)->default(0); // مساحة الأرض
            $table->decimal('building_area', 10, 2)->default(0); // مساحة البناء

            // --- الموقع ---
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('neighborhood_id')->nullable()->constrained('neighborhoods')->nullOnDelete();
            $table->string('street')->nullable();
            $table->string('building_number')->nullable();
            $table->string('postal_code')->nullable();
            $table->enum('direction', ['شمالية', 'جنوبية', 'شرقية', 'غربية', 'شمالية شرقية', 'شمالية غربية', 'جنوبية شرقية', 'جنوبية غربية'])->nullable();
            $table->string('map_url')->nullable(); // رابط الموقع
            $table->decimal('latitude', 10, 7)->nullable(); // خطوط الطول
            $table->decimal('longitude', 10, 7)->nullable(); // خطوط العرض
            $table->text('nearby_places')->nullable(); // المواقع القريبة

            // --- المالك ---
            $table->foreignId('owner_id')->constrained('owners');
            $table->enum('advertiser_role', ['مالك', 'وكيل', 'مطور'])->nullable(); // صفة المعلن
            $table->enum('mortgage_status', ['مرهون', 'غير مرهون'])->default('غير مرهون');
            $table->decimal('mortgage_amount', 15, 2)->default(0);
            $table->enum('rental_status', ['مؤجر', 'غير مؤجر', 'جزئي'])->default('غير مؤجر');
            $table->decimal('annual_income', 15, 2)->default(0); // الإيراد السنوي
            $table->decimal('annual_return_rate', 5, 2)->default(0); // نسبة العائد السنوي %
            $table->integer('tenants_count')->default(0);
            $table->string('contract_duration')->nullable(); // مدة العقود
            $table->string('contract_type_rental')->nullable(); // نوع عقود الإيجار
            $table->text('rental_info')->nullable(); // معلومات إضافية الإيجار
            $table->date('contract_end_date')->nullable();

            // --- التفاصيل ---
            $table->integer('rooms_count')->default(0);
            $table->integer('bathrooms_count')->default(0);
            $table->integer('halls_count')->default(0);
            $table->integer('kitchens_count')->default(0);
            $table->integer('floors_count')->default(0);
            $table->integer('units_count')->default(1); // العدد
            $table->string('dimensions')->nullable(); // الأطوال (25م × 20م)
            $table->decimal('street_width', 6, 2)->default(0); // عرض الشارع
            $table->integer('building_age')->default(0); // عمر العقار
            $table->boolean('is_furnished')->default(false);
            $table->boolean('has_pool')->default(false);
            $table->boolean('has_garden')->default(false);
            $table->boolean('has_elevator')->default(false);
            $table->enum('parking', ['متوفر', 'غير متوفر'])->default('غير متوفر');
            $table->enum('ac_type', ['مركزي', 'سبليت', 'لا يوجد'])->nullable();
            $table->boolean('has_internet')->default(false);
            $table->boolean('has_security')->default(false);
            $table->string('heating_type')->nullable();

            // --- المميزات والوسائط ---
            $table->text('description')->nullable(); // وصف العقار
            $table->text('features')->nullable(); // مميزات العقار
            $table->text('components')->nullable(); // مكونات العقار
            $table->text('additional_info')->nullable(); // معلومات إضافية
            $table->string('photos_url')->nullable(); // رابط الصور
            $table->string('video_url')->nullable(); // رابط الفيديو
            $table->string('virtual_tour_url')->nullable(); // رابط الجولة الافتراضية
            $table->integer('photos_count')->default(0);

            // --- التجهيز الفني ---
            $table->enum('photo_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد');
            $table->enum('design_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد');
            $table->enum('video_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد');
            $table->enum('marketing_status', ['جديد', 'تم', 'جاري', 'معلق', 'ملغي'])->default('جديد');
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->nullOnDelete(); // الموظف المكلف
            $table->decimal('commission_rate', 5, 2)->default(0); // العمولة %

            // --- معلومات النظام ---
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================================
        // جدول مستندات العقار
        // ============================================================
        Schema::create('property_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->enum('type', ['صك', 'رخصة بناء', 'مخططات', 'أخرى']);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_documents');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('owners');
    }
};
