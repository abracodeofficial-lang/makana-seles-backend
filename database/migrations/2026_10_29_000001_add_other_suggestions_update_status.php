<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// إضافة "اقتراحات أخرى" لحالة التحديث بالمهتمين (enum) — بدون doctrine/dbal
return new class extends Migration
{
    private const VALUES = ['تم البيع', 'استفسار', 'طلبات بحث', 'اهتمام أولي', 'اهتمام عالي', 'اقتراحات أخرى'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        $type = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['leads', 'update_status']
        )?->t;
        if ($type && !str_contains($type, 'اقتراحات أخرى')) {
            $enum = "ENUM('" . implode("','", self::VALUES) . "')";
            DB::statement("ALTER TABLE `leads` MODIFY `update_status` $enum NULL DEFAULT 'استفسار'");
        }
    }

    public function down(): void {}
};
