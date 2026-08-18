<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // جدول الحضور والانصراف
        // ============================================================
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable(); // وقت الدخول
            $table->time('check_out')->nullable(); // وقت الخروج
            $table->decimal('worked_hours', 4, 2)->default(0); // ساعات العمل المحسوبة
            $table->enum('status', ['حاضر', 'متأخر', 'غياب', 'إجازة'])->default('غياب');
            $table->decimal('overtime_hours', 4, 2)->default(0); // ساعات إضافية
            $table->decimal('late_deduction', 10, 2)->default(0); // قيمة خصم التأخير
            $table->decimal('early_leave_deduction', 10, 2)->default(0); // قيمة خصم الانصراف المبكر
            $table->decimal('occupancy_allowance', 10, 2)->default(0); // بدل نسبة الانشغال
            $table->string('notes')->nullable();
            $table->enum('source', ['يدوي', 'تلقائي'])->default('يدوي'); // مصدر التحديث
            $table->unique(['employee_id', 'date']); // موظف + يوم = سجل واحد
            $table->timestamps();
        });

        // ============================================================
        // جدول رصيد الإجازات
        // ============================================================
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $table->year('year'); // 2024، 2025
            $table->unsignedSmallInteger('total_days')->default(0); // الرصيد الكلي
            $table->unsignedSmallInteger('used_days')->default(0); // الرصيد المستخدم
            // الرصيد المتبقي = total - used (يُحسب)
            $table->date('last_leave_date')->nullable(); // آخر إجازة
            $table->unique(['employee_id', 'leave_type_id', 'year']);
            $table->timestamps();
        });

        // ============================================================
        // جدول طلبات الإجازة
        // ============================================================
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique(); // LR-001
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedSmallInteger('days_count'); // يُحسب تلقائياً
            $table->text('reason');
            $table->string('attachment_path')->nullable(); // مرفق

            // --- اعتماد المدير (المرحلة 1) ---
            $table->enum('manager_status', ['قيد الانتظار', 'موافق', 'مرفوض', 'إرجاع'])->default('قيد الانتظار');
            $table->text('manager_notes')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('manager_decision_at')->nullable();

            // --- اعتماد HR (المرحلة 2) ---
            $table->enum('hr_status', ['قيد الانتظار', 'موافق', 'مرفوض', 'إرجاع'])->default('قيد الانتظار');
            $table->text('hr_notes')->nullable();
            $table->foreignId('hr_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('hr_decision_at')->nullable();

            // --- الحالة الكلية ---
            $table->enum('final_status', ['قيد المراجعة', 'معتمدة', 'مرفوضة'])->default('قيد المراجعة');

            $table->timestamps();
        });

        // ============================================================
        // جدول الإجازات التلقائية
        // تُنشأ تلقائياً عند اعتماد HR وتُحدِّث سجل الحضور
        // ============================================================
        Schema::create('auto_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedSmallInteger('days_count');
            $table->enum('status', ['معتمدة', 'مرفوضة'])->default('معتمدة');
            $table->string('created_source')->default('تلقائي من HR');
            $table->timestamps();
        });

        // ============================================================
        // جدول طلبات الإذن
        // ============================================================
        Schema::create('permission_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique(); // PR-001
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('type', ['طبي', 'شخصي', 'حكومي']);
            $table->enum('duration', ['ساعة واحدة', 'ساعتين', 'نصف يوم']);
            $table->text('reason');
            $table->datetime('request_datetime'); // تاريخ ووقت الطلب

            // --- اعتماد المدير ---
            $table->enum('manager_status', ['قيد المراجعة', 'موافق', 'مرفوض'])->default('قيد المراجعة');
            $table->text('manager_notes')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('manager_decision_at')->nullable();

            // --- اعتماد HR (تلقائي بعد المدير) ---
            $table->enum('hr_status', ['قيد الانتظار', 'معتمد', 'مرفوض'])->default('قيد الانتظار');
            $table->timestamp('hr_decision_at')->nullable();

            // --- الحالة الكلية ---
            $table->enum('final_status', ['قيد المراجعة', 'موافق', 'مرفوض'])->default('قيد المراجعة');

            $table->timestamps();
        });

        // ============================================================
        // جدول الإشعارات
        // ============================================================
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('type', ['نجاح', 'تحذير', 'معلومة', 'خطأ'])->default('معلومة');
            $table->boolean('is_read')->default(false);
            $table->string('url')->nullable(); // رابط الانتقال
            $table->timestamps();
        });

        // ============================================================
        // جدول الصلاحيات المرنة
        // ============================================================
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // مجموعة فريق الملاك، مجموعة فريق المهتمين...
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_group_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_group_id')->constrained('permission_groups')->cascadeOnDelete();
            $table->string('page_key'); // owners، properties، leads، employees...
            $table->string('page_name'); // الملاك، العقارات، المهتمين...
            $table->boolean('can_view')->default(false);
            $table->boolean('can_add')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_approve')->default(false); // للموافقات
            $table->timestamps();
        });

        Schema::create('employee_permission_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('permission_group_id')->constrained('permission_groups')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'permission_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_permission_groups');
        Schema::dropIfExists('permission_group_pages');
        Schema::dropIfExists('permission_groups');
        Schema::dropIfExists('notifications_log');
        Schema::dropIfExists('permission_requests');
        Schema::dropIfExists('auto_leaves');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('attendance');
    }
};
