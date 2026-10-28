<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// إضافة اتجاه "وسطى" لعمود الاتجاه بالعقارات والمهتمين (enum) — بدون doctrine/dbal
return new class extends Migration
{
    private const VALUES = ['شمالية', 'جنوبية', 'شرقية', 'غربية', 'شمالية شرقية', 'شمالية غربية', 'جنوبية شرقية', 'جنوبية غربية', 'وسطى'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        $enum = "ENUM('" . implode("','", self::VALUES) . "')";
        foreach (['properties', 'leads'] as $table) {
            $type = DB::selectOne(
                'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, 'direction']
            )?->t;
            if ($type && !str_contains($type, 'وسطى')) {
                DB::statement("ALTER TABLE `$table` MODIFY `direction` $enum NULL");
            }
        }
    }

    public function down(): void {}
};
