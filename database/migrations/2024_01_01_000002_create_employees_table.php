<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // جدول الموظفين
        // ============================================================
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // --- المعلومات الأساسية ---
            $table->string('employee_number')->unique(); // E-2024-001
            $table->string('full_name');
            $table->string('national_id')->unique(); // رقم الهوية
            $table->string('phone')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('address')->nullable(); // المدينة، الحي، الشارع
            $table->enum('marital_status', ['أعزب', 'متزوج', 'مطلق', 'أرمل'])->nullable();
            $table->enum('status', ['نشط', 'إيقاف مؤقت', 'منتهي'])->default('نشط');

            // --- المعلومات الوظيفية ---
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('job_title'); // المسمى الوظيفي
            $table->foreignId('contract_type_id')->nullable()->constrained('contract_types')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->date('hire_date'); // تاريخ التعيين

            // --- المعلومات المالية ---
            $table->decimal('basic_salary', 12, 2)->default(0); // الراتب الأساسي
            $table->decimal('housing_allowance', 12, 2)->default(0); // بدل سكن
            $table->decimal('transport_allowance', 12, 2)->default(0); // بدل مواصلات
            $table->decimal('phone_allowance', 12, 2)->default(0); // بدل اتصالات
            $table->decimal('other_allowances', 12, 2)->default(0); // بدلات أخرى
            // إجمالي الدخل يُحسب: basic + housing + transport + phone + other
            $table->decimal('commission_rate', 5, 2)->default(0); // نسبة العمولة %
            $table->decimal('min_sales_target', 12, 2)->default(0); // الحد الأدنى للمبيعات

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        // ============================================================
        // جدول سجل تعديلات الراتب
        // ============================================================
        Schema::create('salary_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('old_salary', 12, 2);
            $table->decimal('new_salary', 12, 2);
            $table->text('reason'); // سبب التعديل
            $table->foreignId('approved_by')->constrained('employees'); // المعتمد من
            $table->date('effective_date'); // تاريخ التعديل
            $table->timestamps();
        });

        // ============================================================
        // جدول وثائق الموظف
        // ============================================================
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('type', ['سيرة ذاتية', 'شهادات', 'هوية', 'جواز سفر', 'عقد وظيفي', 'أخرى']);
            $table->string('name'); // اسم وصفي للمستند
            $table->string('file_path'); // مسار الملف
            $table->string('file_name'); // اسم الملف الأصلي
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('salary_history');
        Schema::dropIfExists('employees');
    }
};
