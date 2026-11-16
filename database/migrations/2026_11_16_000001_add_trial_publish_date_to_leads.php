<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'trial_publish_date')) {
            Schema::table('leads', function (Blueprint $table) {
                // تاريخ النشر التجريبي — نص حر، نفس حقل العقارات
                $table->string('trial_publish_date', 100)->nullable()->after('direction');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'trial_publish_date')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('trial_publish_date');
            });
        }
    }
};
