<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// نوع مستند جديد "ملف المشروع" (enum) — بدون doctrine/dbal
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        $type = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['property_documents', 'type']
        )?->t;
        if ($type && !str_contains($type, 'ملف المشروع')) {
            DB::statement("ALTER TABLE `property_documents` MODIFY `type` ENUM('صك','رخصة بناء','مخططات','أخرى','ملف المشروع') NOT NULL");
        }
    }

    public function down(): void
    {
        // ما بنشيل القيمة حتى ما نخسر ملفات مرفوعة
    }
};
