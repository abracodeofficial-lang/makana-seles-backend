<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تخصيص موظف أوبريشن لكل نوع عقار — قابل للتعديل الكامل من الإعدادات
        if (!Schema::hasTable('operation_property_type_assignments')) {
            Schema::create('operation_property_type_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('property_type_id')->constrained('property_types')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['employee_id', 'property_type_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_property_type_assignments');
    }
};
