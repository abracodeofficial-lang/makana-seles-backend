<?php

namespace App\Services\Publishing\Platforms;

use App\Models\Property;
use App\Models\PropertyPublication;
use App\Services\Publishing\BasePublisher;
use App\Services\Publishing\ListingPayload;
use App\Services\Publishing\PublishResult;

// عقار: ما في API — بنجهّز حزمة نشر، والناشر بينشر بإيده وبيلصق الرابط
class AqarManualPublisher extends BasePublisher
{
    public function key(): string { return 'aqar'; }
    protected function label(): string { return 'عقار Aqar'; }

    public function describe(): array
    {
        return array_merge(parent::describe(), ['manual' => true, 'notice' => 'نشر يدوي: انسخ البيانات وانشر على عقار، وبعدها الصق الرابط']);
    }

    public function publish(Property $property, PropertyPublication $publication): PublishResult
    {
        return PublishResult::awaitingManual('تجهيز حزمة النشر');
    }

    public function update(Property $property, PropertyPublication $publication): PublishResult
    {
        return PublishResult::awaitingManual('تجهيز حزمة تحديث');
    }

    public function unpublish(Property $property, PropertyPublication $publication): PublishResult
    {
        return PublishResult::unpublished('إيقاف', 'تذكير: احذف الإعلان من عقار بنفسك');
    }

    // حزمة النشر: كل جزء له زر نسخ
    public static function pack(Property $property): array
    {
        $d = ListingPayload::build($property);
        $money = number_format($d['price']);

        $specs = array_filter([
            'النوع'     => $d['type'],
            'الغرض'     => $d['purpose'],
            'السعر'     => "$money ريال",
            'المساحة'   => $d['area'] ? number_format($d['area'], 2) . ' م²' : null,
            'المدينة'   => $d['city'],
            'الحي'      => $d['neighborhood'],
            'الواجهة'   => $d['facade'],
            'عرض الشارع'=> $d['street_width'] ?: null,
            'الغرف'     => $d['rooms'] ?: null,
            'دورات المياه' => $d['bathrooms'] ?: null,
            'الصالات'   => $d['halls'] ?: null,
        ]);

        return [
            ['key' => 'title',       'label' => 'العنوان',  'text' => (string) $d['title']],
            ['key' => 'description', 'label' => 'الوصف',    'text' => $d['description_text']],
            ['key' => 'specs',       'label' => 'المواصفات', 'text' => collect($specs)->map(fn($v, $k) => "$k: $v")->implode("\n")],
            ['key' => 'license',     'label' => 'رخصة الإعلان', 'text' => "رقم رخصة الإعلان: {$d['ad_license_number']}\nتنتهي: {$d['ad_license_expiry']}\nرقم رخصة فال: {$d['fal_license_number']}"],
        ];
    }
}
