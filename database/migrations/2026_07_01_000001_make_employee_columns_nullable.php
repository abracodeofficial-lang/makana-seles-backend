<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // هذه الحقول NOT NULL بدون default — تفشل عند إنشاء موظف ببيانات أساسية فقط
            $table->string('job_title')->nullable()->change();
            $table->date('hire_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('job_title')->nullable(false)->change();
            $table->date('hire_date')->nullable(false)->change();
        });
    }
};
