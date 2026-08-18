<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitiesSeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'شمال الرياض', 'code' => 'RN'],
            ['name' => 'شرق الرياض',  'code' => 'RE'],
            ['name' => 'غرب الرياض',  'code' => 'RW'],
            ['name' => 'جنوب الرياض', 'code' => 'RS'],
            ['name' => 'وسط الرياض',  'code' => 'RC'],
            ['name' => 'تبوك',         'code' => 'TB'],
        ];

        foreach ($cities as $city) {
            DB::table('cities')->insertOrIgnore([
                'name'       => $city['name'],
                'code'       => $city['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // الأحياء مرتبة بالمدينة
        $neighborhoods = [
            // شمال الرياض (id=1)
            'RN' => [
                'الياسمين', 'النرجس', 'القيروان', 'حطين', 'الملقا',
                'العارض', 'الغدير', 'الفلاح', 'الواحة', 'غرناطة',
            ],
            // شرق الرياض (id=2)
            'RE' => [
                'الرمال', 'المونسية', 'الفيحاء', 'الملك فيصل',
                'النسيم', 'الروضة', 'الحمراء',
            ],
            // غرب الرياض (id=3)
            'RW' => [
                'ظهرة لبن', 'الأندلس', 'نمار', 'العزيزية',
                'المهدية', 'الدريهيمية', 'الشفا',
            ],
            // جنوب الرياض (id=4)
            'RS' => [
                'السويدي', 'الفاروق', 'المصفاة', 'الشعلة',
                'السلي', 'عرقة', 'الجنادرية',
            ],
            // وسط الرياض (id=5)
            'RC' => [
                'الملز', 'البطحاء', 'المربع', 'الديرة',
                'العليا', 'السفارات',
            ],
            // تبوك (id=6)
            'TB' => [
                'المروج', 'الروضة', 'الصناعية', 'الريان',
                'الوادي', 'النزهة',
            ],
        ];

        foreach ($neighborhoods as $cityCode => $areas) {
            $cityId = DB::table('cities')->where('code', $cityCode)->value('id');
            if (!$cityId) continue;

            foreach ($areas as $area) {
                DB::table('neighborhoods')->insertOrIgnore([
                    'name'       => $area,
                    'city_id'    => $cityId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
