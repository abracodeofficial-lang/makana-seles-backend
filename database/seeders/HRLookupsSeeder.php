<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HRLookupsSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // الأقسام
        // ============================================================
        $departments = [
            'المبيعات',
            'التسويق',
            'المالية',
            'الموارد البشرية',
            'تقنية المعلومات',
            'الإدارة',
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->insertOrIgnore([
                'name'       => $dept,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================================
        // أنواع التعاقد
        // ============================================================
        $contractTypes = [
            ['name' => 'دوام كامل',          'details' => 'عقد عمل بدوام كامل غير محدد المدة'],
            ['name' => 'دوام جزئي',          'details' => 'عقد عمل بدوام جزئي'],
            ['name' => 'مؤقت',               'details' => 'عقد عمل مؤقت لمدة محددة'],
            ['name' => 'عقد محدد المدة',     'details' => 'عقد عمل محدد المدة قابل للتجديد'],
            ['name' => 'عمل حر',             'details' => 'عقد عمل حر أو فريلانس'],
            ['name' => 'تدريب',              'details' => 'عقد تدريب أو تأهيل'],
            ['name' => 'تطوع',               'details' => 'عقد تطوع بدون مقابل مادي'],
        ];

        foreach ($contractTypes as $type) {
            DB::table('contract_types')->insertOrIgnore([
                'name'       => $type['name'],
                'details'    => $type['details'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================================
        // الشفتات
        // ============================================================
        $shifts = [
            [
                'name'                    => 'صباحي',
                'start_time'              => '08:00:00',
                'end_time'                => '17:00:00',
                'late_tolerance_minutes'  => 15,
            ],
            [
                'name'                    => 'مسائي',
                'start_time'              => '15:00:00',
                'end_time'                => '00:00:00',
                'late_tolerance_minutes'  => 15,
            ],
            [
                'name'                    => 'ليلي',
                'start_time'              => '22:00:00',
                'end_time'                => '07:00:00',
                'late_tolerance_minutes'  => 15,
            ],
            [
                'name'                    => 'مرن',
                'start_time'              => '08:00:00',
                'end_time'                => '17:00:00',
                'late_tolerance_minutes'  => 30,
            ],
        ];

        foreach ($shifts as $shift) {
            DB::table('shifts')->insertOrIgnore([
                'name'                   => $shift['name'],
                'start_time'             => $shift['start_time'],
                'end_time'               => $shift['end_time'],
                'late_tolerance_minutes' => $shift['late_tolerance_minutes'],
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        // ============================================================
        // أنواع الإجازات
        // ============================================================
        $leaveTypes = [
            [
                'name'                => 'إجازة سنوية',
                'total_days'          => 30,
                'requires_attachment' => false,
            ],
            [
                'name'                => 'إجازة مرضية',
                'total_days'          => 15,
                'requires_attachment' => true, // تحتاج تقرير طبي
            ],
            [
                'name'                => 'إجازة اعتيادية',
                'total_days'          => 7,
                'requires_attachment' => false,
            ],
            [
                'name'                => 'إجازة عارضة',
                'total_days'          => 5,
                'requires_attachment' => false,
            ],
            [
                'name'                => 'إجازة بدون أجر',
                'total_days'          => 999, // غير محدودة (∞)
                'requires_attachment' => false,
            ],
        ];

        foreach ($leaveTypes as $type) {
            DB::table('leave_types')->insertOrIgnore([
                'name'                => $type['name'],
                'total_days'          => $type['total_days'],
                'requires_attachment' => $type['requires_attachment'],
                'is_active'           => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        // ============================================================
        // إعدادات الدوام (سجل واحد فقط)
        // ============================================================
        if (DB::table('attendance_settings')->count() === 0) {
            DB::table('attendance_settings')->insert([
                'late_tolerance_minutes'      => 15,  // دقائق السماح بالتأخير
                'overtime_multiplier'          => 1.5, // معامل الساعة الإضافية
                'deduction_after_late_days'    => 3,   // بعد كم يوم تأخير يبدأ الخصم
                'created_at'                   => now(),
                'updated_at'                   => now(),
            ]);
        }
    }
}
