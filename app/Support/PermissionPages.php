<?php

namespace App\Support;

class PermissionPages
{
    // مصدر واحد لقائمة صفحات النظام القابلة للتحكم بصلاحياتها
    public static function all(): array
    {
        return [
            'properties'     => 'العقارات',
            'owners'         => 'الملاك',
            'leads'          => 'المهتمون',
            'employees'      => 'الموظفون',
            'attendance'     => 'الحضور والانصراف',
            'leave_requests' => 'الإجازات',
            'permissions'    => 'الإذونات',
            'salary'         => 'الرواتب',
            'reports'        => 'التقارير',
            'settings'       => 'الإعدادات',
            'notifications'  => 'الإشعارات',
            'roles'          => 'الأدوار والصلاحيات',
        ];
    }
}
