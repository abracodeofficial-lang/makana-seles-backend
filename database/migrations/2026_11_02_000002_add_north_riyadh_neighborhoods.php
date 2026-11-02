<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// أحياء شمال الرياض — اللي موجود بنفس الاسم (مع اختلاف الهمزة/التاء المربوطة/المسافات/كلمة "حي") ما بينضاف
return new class extends Migration
{
    private const NAMES = [
        'حي مطار الملك خالد', 'النرجس', 'العارض', 'القيروان', 'الياسمين', 'الملقا', 'جامعة الإمام محمد بن سعود الإسلامية',
        'الندى', 'الربيع', 'الصحافة', 'الفلاح', 'الوادي', 'النفل', 'الغدير', 'العقيق', 'حطين', 'الازدهار', 'التعاون', 'المصيف',
        'المروج', 'النخيل', 'المغرزات', 'النزهة', 'المرسلات', 'الملك فهد', 'المحمدية', 'حي جامعة الملك سعود', 'الخزامي',
        'الملك عبدالله', 'الواحة', 'صلاح الدين', 'الورود', 'الرحمانية', 'الرائد', 'الملك عبدالعزيز', 'السليمانية', 'العليا',
        'المعذر الشرقي', 'أم الحمام الشرقي',
    ];

    public function up(): void
    {
        $cityId = DB::table('cities')->where('name', 'شمال الرياض')->whereNull('deleted_at')->value('id');
        if (!$cityId) {
            // قاعدة جديدة لسا ما انعبّت مدنها
            logger()->warning('add_north_riyadh_neighborhoods: مدينة "شمال الرياض" غير موجودة — ما انضافت أحياء');
            return;
        }

        $existing = DB::table('neighborhoods')->where('city_id', $cityId)->whereNull('deleted_at')->pluck('name')
            ->map(fn($n) => $this->normalize($n))->all();

        foreach (self::NAMES as $name) {
            $key = $this->normalize($name);
            if (in_array($key, $existing, true)) continue;

            DB::table('neighborhoods')->insert(['name' => $name, 'city_id' => $cityId, 'created_at' => now(), 'updated_at' => now()]);
            $existing[] = $key;
        }
    }

    public function down(): void {}

    private function normalize(string $name): string
    {
        $name = preg_replace('/^حي\s+/u', '', trim($name));
        $name = preg_replace('/\s+/u', '', $name);
        return str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $name);
    }
};
