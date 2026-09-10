<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // حالة الأوبريشن قبل التحويل للأخصائي
            $table->enum('operation_status', ['يبغى تواصل هاتفي', 'عنده استفسارات أكثر', 'اهتمام مبدئي'])
                  ->nullable()->after('operation_employee_id');

            // مرحلة الأخصائي بعد التحويل
            $table->enum('specialist_stage', ['تواصل', 'معلومات واستفسارات', 'زيارة', 'إقناع', 'تفاوض', 'تفاهم', 'حجز'])
                  ->nullable()->after('broker_employee_id');

            // توقيت تحويل المهتم من الأوبريشن للأخصائي
            $table->timestamp('assigned_to_specialist_at')->nullable()->after('specialist_stage');

            // المبلغ المتفق عليه عند الوصول لمرحلة حجز
            $table->decimal('agreed_amount', 15, 2)->nullable()->after('assigned_to_specialist_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['operation_status', 'specialist_stage', 'assigned_to_specialist_at', 'agreed_amount']);
        });
    }
};
