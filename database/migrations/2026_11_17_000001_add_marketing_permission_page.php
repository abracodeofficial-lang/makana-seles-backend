<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// قسم التصميم والتسويق: صلاحية صفحة جديدة للأدمن + دور جاهز للمصممين
return new class extends Migration
{
    public function up(): void
    {
        $page = fn(int $groupId, array $can) => DB::table('permission_group_pages')->updateOrInsert(
            ['permission_group_id' => $groupId, 'page_key' => 'marketing'],
            ['page_name' => 'التصميم والتسويق', 'created_at' => now(), 'updated_at' => now()] + array_merge(
                ['can_view' => 0, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0, 'can_export' => 0, 'can_approve' => 0],
                array_fill_keys($can, 1)
            )
        );
        $all = ['can_view', 'can_add', 'can_edit', 'can_delete', 'can_export', 'can_approve'];

        // كل مجموعة عندها تحكم كامل بالأدوار = أدمن
        $admins = DB::table('permission_group_pages')
            ->where('page_key', 'roles')->where('can_view', 1)->where('can_edit', 1)->where('can_delete', 1)
            ->pluck('permission_group_id')
            ->merge(DB::table('permission_groups')->where('name', 'مدير النظام')->pluck('id'))
            ->unique();
        foreach ($admins as $id) $page($id, $all);

        $name = 'التصميم والتسويق';
        $designers = DB::table('permission_groups')->where('name', $name)->whereNull('deleted_at')->value('id')
            ?: DB::table('permission_groups')->insertGetId([
                'name' => $name, 'description' => 'أدوات التصميم والتسويق (ضغط الصور وغيرها)',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        $page($designers, $all);
    }

    public function down(): void
    {
        DB::table('permission_group_pages')->where('page_key', 'marketing')->delete();
    }
};
