<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// إضافة "وسيط" لصفة المعلن بالعقارات (enum) — بدون doctrine/dbal
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        $type = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['properties', 'advertiser_role']
        )?->t;
        if ($type && !str_contains($type, 'وسيط')) {
            DB::statement("ALTER TABLE `properties` MODIFY `advertiser_role` ENUM('مالك','وكيل','وسيط','مطور') NULL");
        }
    }

    public function down(): void {}
};
