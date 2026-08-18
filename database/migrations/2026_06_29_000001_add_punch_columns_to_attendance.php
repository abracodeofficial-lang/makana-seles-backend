<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_minutes')->default(0)->after('status');
            $table->time('break_start')->nullable()->after('check_out');
            $table->time('break_end')->nullable()->after('break_start');
            $table->boolean('is_approved')->default(false)->after('notes');
            $table->foreignId('approved_by')->nullable()->after('is_approved')
                  ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['late_minutes', 'break_start', 'break_end', 'is_approved', 'approved_by']);
        });
    }
};
