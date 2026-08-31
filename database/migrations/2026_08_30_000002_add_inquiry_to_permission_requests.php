<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        // إضافة حالة "إرجاع" لطلب توضيح من الموظف (نفس فكرة طلبات الإجازة)
        DB::statement("ALTER TABLE permission_requests MODIFY manager_status ENUM('قيد المراجعة', 'موافق', 'مرفوض', 'إرجاع') DEFAULT 'قيد المراجعة'");

        Schema::table('permission_requests', function (Blueprint $table) {
            $table->text('clarification')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('permission_requests', function (Blueprint $table) {
            $table->dropColumn('clarification');
        });

        DB::statement("ALTER TABLE permission_requests MODIFY manager_status ENUM('قيد المراجعة', 'موافق', 'مرفوض') DEFAULT 'قيد المراجعة'");
    }
};
