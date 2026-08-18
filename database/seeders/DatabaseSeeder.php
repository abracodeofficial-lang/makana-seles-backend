<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * ترتيب التشغيل مهم جداً — لا تغيره
     * كل seeder يعتمد على بيانات الـ seeder اللي قبله
     */
    public function run(): void
    {
        $this->command->info('🚀 بدء تشغيل الـ Seeders...');

        // 1. جداول الإعدادات المشتركة (لا تعتمد على أي جدول آخر)
        $this->command->info('📍 المدن والأحياء...');
        $this->call(CitiesSeeder::class);

        // 2. جداول العقارات
        $this->command->info('🏠 بيانات العقارات الأساسية...');
        $this->call(PropertyLookupsSeeder::class);

        // 3. جداول الموارد البشرية
        $this->command->info('👥 بيانات الموارد البشرية...');
        $this->call(HRLookupsSeeder::class);

        // 4. مجموعات الصلاحيات (تعتمد على departments)
        $this->command->info('🔐 مجموعات الصلاحيات...');
        $this->call(PermissionGroupsSeeder::class);

        // 5. موظف المدير (يعتمد على departments + contract_types + shifts + leave_types + permission_groups)
        $this->command->info('👤 حساب المدير...');
        $this->call(AdminEmployeeSeeder::class);

        $this->command->info('✅ تم تشغيل جميع الـ Seeders بنجاح!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('📧 البريد: admin@company.com');
        $this->command->info('🔑 كلمة المرور: Admin@123456');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
