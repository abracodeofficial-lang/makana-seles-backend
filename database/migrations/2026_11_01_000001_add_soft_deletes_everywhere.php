<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// كل عمليات الحذف صارت ناعمة: deleted_at + deleted_by (مين حذف)
return new class extends Migration
{
    private const TABLES = [
        'properties', 'owners', 'leads', 'property_documents', 'employee_documents',
        'cities', 'neighborhoods', 'property_types', 'shifts', 'leave_types', 'permission_durations',
        'permission_groups', 'operation_property_type_assignments',
        'contact_lists', 'wa_campaigns', 'notifications_log',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (!Schema::hasTable($name)) continue;

            Schema::table($name, function (Blueprint $table) use ($name) {
                if (!Schema::hasColumn($name, 'deleted_at')) $table->softDeletes();
                if (!Schema::hasColumn($name, 'deleted_by')) $table->unsignedBigInteger('deleted_by')->nullable();
            });
        }
    }

    public function down(): void {}
};
