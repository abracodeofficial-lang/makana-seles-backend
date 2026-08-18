<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PropertyLookupsSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================================
        // أنواع العقارات
        // ============================================================
        $propertyTypes = [
            'شقة', 'فلة', 'عمارة', 'أرض', 'أدوار', 'بلك',
            'مكتب', 'محل', 'مستودع', 'دور',
        ];

        foreach ($propertyTypes as $type) {
            DB::table('property_types')->insertOrIgnore([
                'name'       => $type,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================================
        // أنواع التسويق
        // ============================================================
        $marketingTypes = [
            ['name' => 'ملاك أفراد',              'ad_number' => null, 'count' => 0],
            ['name' => 'مزادات',                   'ad_number' => null, 'count' => 0],
            ['name' => 'مشاريع على الخارطة',       'ad_number' => null, 'count' => 0],
            ['name' => 'مشاريع جاهزة',             'ad_number' => null, 'count' => 0],
        ];

        foreach ($marketingTypes as $type) {
            DB::table('marketing_types')->insertOrIgnore([
                'name'       => $type['name'],
                'ad_number'  => $type['ad_number'],
                'count'      => $type['count'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================================
        // أنواع الاستخدام
        // ============================================================
        $usageTypes = [
            ['name' => 'سكني',                  'details' => 'للسكن الخاص'],
            ['name' => 'تجاري',                 'details' => 'للأنشطة التجارية'],
            ['name' => 'سكني تجاري',            'details' => 'مزيج سكني وتجاري'],
            ['name' => 'سكني تجاري مكتبي',      'details' => 'مزيج سكني وتجاري ومكتبي'],
            ['name' => 'مستودعات',              'details' => 'للتخزين والمستودعات'],
            ['name' => 'زراعي',                 'details' => 'للأراضي الزراعية'],
            ['name' => 'صناعات خفيفة',          'details' => 'للصناعات الخفيفة'],
            ['name' => 'صناعات ثقيلة',          'details' => 'للصناعات الثقيلة'],
        ];

        foreach ($usageTypes as $type) {
            DB::table('usage_types')->insertOrIgnore([
                'name'       => $type['name'],
                'details'    => $type['details'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ============================================================
        // طرق الدفع
        // ============================================================
        $paymentMethods = [
            ['name' => 'بنك',       'code' => 'BANK'],
            ['name' => 'كاش',       'code' => 'CASH'],
            ['name' => 'بنك وكاش',  'code' => 'BOTH'],
        ];

        foreach ($paymentMethods as $method) {
            DB::table('payment_methods')->insertOrIgnore([
                'name'       => $method['name'],
                'code'       => $method['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
