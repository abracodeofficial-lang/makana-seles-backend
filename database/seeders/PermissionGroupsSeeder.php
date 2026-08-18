<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionGroupsSeeder extends Seeder
{
    public function run(): void
    {
        // الصفحات المتاحة في النظام
        $pages = [
            ['key' => 'properties',   'name' => 'العقارات'],
            ['key' => 'owners',        'name' => 'الملاك'],
            ['key' => 'leads',         'name' => 'المهتمون'],
            ['key' => 'employees',     'name' => 'الموظفون'],
            ['key' => 'attendance',    'name' => 'الحضور والانصراف'],
            ['key' => 'leave_requests','name' => 'الإجازات'],
            ['key' => 'permissions',   'name' => 'الإذونات'],
            ['key' => 'salary',        'name' => 'الرواتب'],
            ['key' => 'reports',       'name' => 'التقارير'],
            ['key' => 'settings',      'name' => 'الإعدادات'],
            ['key' => 'notifications', 'name' => 'الإشعارات'],
        ];

        // ============================================================
        // المجموعة 1: مدير النظام — كل الصلاحيات
        // ============================================================
        $adminId = DB::table('permission_groups')->insertGetId([
            'name'        => 'مدير النظام',
            'description' => 'صلاحيات كاملة على جميع أقسام النظام',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        foreach ($pages as $page) {
            DB::table('permission_group_pages')->insert([
                'permission_group_id' => $adminId,
                'page_key'            => $page['key'],
                'page_name'           => $page['name'],
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

        // ============================================================
        // المجموعة 2: فريق مبيعات الملاك
        // ============================================================
        $salesOwnersId = DB::table('permission_groups')->insertGetId([
            'name'        => 'فريق مبيعات الملاك',
            'description' => 'صلاحيات إدارة العقارات والملاك فقط',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $salesOwnersPermissions = [
            'properties'    => ['view' => true,  'add' => true,  'edit' => true,  'delete' => false, 'export' => true,  'approve' => false],
            'owners'        => ['view' => true,  'add' => true,  'edit' => true,  'delete' => false, 'export' => true,  'approve' => false],
            'leads'         => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'employees'     => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'attendance'    => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'leave_requests'=> ['view' => false, 'add' => true,  'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'permissions'   => ['view' => false, 'add' => true,  'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'salary'        => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'reports'       => ['view' => true,  'add' => false, 'edit' => false, 'delete' => false, 'export' => true,  'approve' => false],
            'settings'      => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'notifications' => ['view' => true,  'add' => false, 'edit' => false, 'delete' => true,  'export' => false, 'approve' => false],
        ];

        $this->insertGroupPages($salesOwnersId, $pages, $salesOwnersPermissions);

        // ============================================================
        // المجموعة 3: فريق مبيعات المهتمين
        // ============================================================
        $salesLeadsId = DB::table('permission_groups')->insertGetId([
            'name'        => 'فريق مبيعات المهتمين',
            'description' => 'صلاحيات إدارة المهتمين والعقارات (عرض فقط)',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $salesLeadsPermissions = [
            'properties'    => ['view' => true,  'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'owners'        => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'leads'         => ['view' => true,  'add' => true,  'edit' => true,  'delete' => false, 'export' => true,  'approve' => false],
            'employees'     => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'attendance'    => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'leave_requests'=> ['view' => false, 'add' => true,  'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'permissions'   => ['view' => false, 'add' => true,  'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'salary'        => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'reports'       => ['view' => true,  'add' => false, 'edit' => false, 'delete' => false, 'export' => true,  'approve' => false],
            'settings'      => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'notifications' => ['view' => true,  'add' => false, 'edit' => false, 'delete' => true,  'export' => false, 'approve' => false],
        ];

        $this->insertGroupPages($salesLeadsId, $pages, $salesLeadsPermissions);

        // ============================================================
        // المجموعة 4: الموارد البشرية
        // ============================================================
        $hrId = DB::table('permission_groups')->insertGetId([
            'name'        => 'الموارد البشرية',
            'description' => 'صلاحيات إدارة الموظفين والحضور والإجازات',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $hrPermissions = [
            'properties'    => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'owners'        => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'leads'         => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'employees'     => ['view' => true,  'add' => true,  'edit' => true,  'delete' => false, 'export' => true,  'approve' => false],
            'attendance'    => ['view' => true,  'add' => true,  'edit' => true,  'delete' => false, 'export' => true,  'approve' => false],
            'leave_requests'=> ['view' => true,  'add' => true,  'edit' => false, 'delete' => false, 'export' => true,  'approve' => true],
            'permissions'   => ['view' => true,  'add' => true,  'edit' => false, 'delete' => false, 'export' => true,  'approve' => true],
            'salary'        => ['view' => true,  'add' => false, 'edit' => true,  'delete' => false, 'export' => true,  'approve' => false],
            'reports'       => ['view' => true,  'add' => false, 'edit' => false, 'delete' => false, 'export' => true,  'approve' => false],
            'settings'      => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'notifications' => ['view' => true,  'add' => false, 'edit' => false, 'delete' => true,  'export' => false, 'approve' => false],
        ];

        $this->insertGroupPages($hrId, $pages, $hrPermissions);

        // ============================================================
        // المجموعة 5: موظف عادي — بروفايل شخصي فقط
        // ============================================================
        $employeeId = DB::table('permission_groups')->insertGetId([
            'name'        => 'موظف عادي',
            'description' => 'عرض البروفايل الشخصي وتقديم الطلبات فقط',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $employeePermissions = [
            'properties'    => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'owners'        => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'leads'         => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'employees'     => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'attendance'    => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'leave_requests'=> ['view' => false, 'add' => true,  'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'permissions'   => ['view' => false, 'add' => true,  'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'salary'        => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'reports'       => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'settings'      => ['view' => false, 'add' => false, 'edit' => false, 'delete' => false, 'export' => false, 'approve' => false],
            'notifications' => ['view' => true,  'add' => false, 'edit' => false, 'delete' => true,  'export' => false, 'approve' => false],
        ];

        $this->insertGroupPages($employeeId, $pages, $employeePermissions);
    }

    // ============================================================
    // Helper
    // ============================================================
    private function insertGroupPages(int $groupId, array $pages, array $permissions): void
    {
        foreach ($pages as $page) {
            $p = $permissions[$page['key']] ?? [];
            DB::table('permission_group_pages')->insert([
                'permission_group_id' => $groupId,
                'page_key'            => $page['key'],
                'page_name'           => $page['name'],
                'can_view'            => $p['view']    ?? false,
                'can_add'             => $p['add']     ?? false,
                'can_edit'            => $p['edit']    ?? false,
                'can_delete'          => $p['delete']  ?? false,
                'can_export'          => $p['export']  ?? false,
                'can_approve'         => $p['approve'] ?? false,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }
}
