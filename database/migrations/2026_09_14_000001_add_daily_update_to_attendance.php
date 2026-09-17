<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('attendance', 'daily_update')) {
            Schema::table('attendance', function (Blueprint $table) {
                // التحديث اليومي يلي بيكتبه الموظف عند بصمة الانصراف — يشوفه المدير والـHR
                $table->text('daily_update')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn('daily_update');
        });
    }
};
