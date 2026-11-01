<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// سجل العمليات: مين عمل شو، على أي سجل، وبأي وقت
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) return;

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('employee_name')->nullable();        // نسخة من الاسم وقت العملية
            $table->string('action', 16)->index();              // created, updated, deleted, restored
            $table->string('subject_type', 60)->index();        // اسم الموديل
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('changes')->nullable();                // [{field, label, old, new}]
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
