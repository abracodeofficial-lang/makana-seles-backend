<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// حقول صارت اختيارية بطلب المستخدم:
//   • اسم المالك واسم المهتم (أحياناً ما بيكون معنا غير الرقم)
//   • مالك العقار (owner_id) — العقار بينضاف بدون مالك ويتحدد بعدين
// SQL مباشر لأن ->change() بيحتاج doctrine/dbal (مش مثبّت). آمنة للتشغيل أكتر من مرة.
return new class extends Migration
{
    private const COLUMNS = [
        ['owners',     'name',     'VARCHAR(255)'],
        ['leads',      'name',     'VARCHAR(255)'],
        ['properties', 'owner_id', 'BIGINT UNSIGNED'],
    ];

    private function isNullable(string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT IS_NULLABLE AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        return $row && $row->n === 'YES';
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        foreach (self::COLUMNS as [$table, $column, $type]) {
            if (!$this->isNullable($table, $column)) {
                DB::statement("ALTER TABLE `$table` MODIFY `$column` $type NULL");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        DB::statement("UPDATE `owners` SET `name` = '' WHERE `name` IS NULL");
        DB::statement("UPDATE `leads` SET `name` = '' WHERE `name` IS NULL");
        DB::statement('ALTER TABLE `owners` MODIFY `name` VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE `leads` MODIFY `name` VARCHAR(255) NOT NULL');
        // properties.owner_id بيضل nullable — ما في قيمة صحيحة نحطها للعقارات بدون مالك
    }
};
