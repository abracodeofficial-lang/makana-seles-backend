<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // صفحة صلاحية جديدة "المالية" — تُستخدم لتوجيه إشعارات الحجز لمن لديه صلاحية اعتماد عليها
        $adminGroupId = DB::table('permission_groups')->where('name', 'مدير النظام')->value('id');

        if ($adminGroupId && !DB::table('permission_group_pages')
                ->where('permission_group_id', $adminGroupId)
                ->where('page_key', 'finance')
                ->exists()) {
            DB::table('permission_group_pages')->insert([
                'permission_group_id' => $adminGroupId,
                'page_key'            => 'finance',
                'page_name'           => 'المالية',
                'can_view'            => true,
                'can_add'             => true,
                'can_edit'            => true,
                'can_delete'          => true,
                'can_export'          => true,
                'can_approve'         => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permission_group_pages')
            ->whereIn('permission_group_id', DB::table('permission_groups')->where('name', 'مدير النظام')->pluck('id'))
            ->where('page_key', 'finance')
            ->delete();
    }
};
