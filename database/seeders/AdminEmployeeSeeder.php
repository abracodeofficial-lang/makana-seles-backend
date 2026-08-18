<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // موظف المدير الأول (admin)
        // ============================================================
        $adminId = DB::table('employees')->insertGetId([
            'employee_number'    => 'E-2024-001',
            'full_name'          => 'أحمد محمد السعيد',
            'national_id'        => '1000000001',
            'phone'              => '0501234567',
            'email'              => 'admin@company.com',
            'password'           => Hash::make('Admin@123456'),
            'address'            => 'الرياض، حي النرجس، شارع التحلية',
            'marital_status'     => 'متزوج',
            'status'             => 'نشط',
            'department_id'      => DB::table('departments')->where('name', 'الإدارة')->value('id'),
            'job_title'          => 'مدير النظام',
            'contract_type_id'   => DB::table('contract_types')->where('name', 'دوام كامل')->value('id'),
            'shift_id'           => DB::table('shifts')->where('name', 'صباحي')->value('id'),
            'hire_date'          => '2024-01-15',
            'basic_salary'       => 15000,
            'housing_allowance'  => 3000,
            'transport_allowance'=> 1000,
            'phone_allowance'    => 500,
            'other_allowances'   => 0,
            'commission_rate'    => 0,
            'min_sales_target'   => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // ربط المدير بمجموعة صلاحيات "مدير النظام"
        $adminGroupId = DB::table('permission_groups')->where('name', 'مدير النظام')->value('id');

        DB::table('employee_permission_groups')->insertOrIgnore([
            'employee_id'         => $adminId,
            'permission_group_id' => $adminGroupId,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // ============================================================
        // رصيد إجازات المدير للسنة الحالية
        // ============================================================
        $leaveTypes = DB::table('leave_types')->get();
        foreach ($leaveTypes as $leaveType) {
            DB::table('leave_balances')->insertOrIgnore([
                'employee_id'    => $adminId,
                'leave_type_id'  => $leaveType->id,
                'year'           => now()->year,
                'total_days'     => $leaveType->total_days === 999 ? 999 : $leaveType->total_days,
                'used_days'      => 0,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $this->command->info('✅ تم إنشاء حساب المدير: admin@company.com / Admin@123456');
    }
}
