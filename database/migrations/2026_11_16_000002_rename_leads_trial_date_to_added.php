<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// المهتمين: الحقل هو "تاريخ الإضافة التجريبي" مش "النشر" — نقل العمود لاسم صحيح
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'trial_added_date')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('trial_added_date', 100)->nullable()->after('direction');
            });
        }
        if (Schema::hasColumn('leads', 'trial_publish_date')) {
            DB::table('leads')->whereNotNull('trial_publish_date')->update(['trial_added_date' => DB::raw('trial_publish_date')]);
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('trial_publish_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'trial_added_date')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('trial_added_date');
            });
        }
    }
};
