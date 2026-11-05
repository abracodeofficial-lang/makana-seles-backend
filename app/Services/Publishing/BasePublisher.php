<?php

namespace App\Services\Publishing;

use App\Models\Property;

abstract class BasePublisher implements PlatformPublisher
{
    public function describe(): array
    {
        return ['key' => $this->key(), 'label' => $this->label(), 'manual' => false, 'notice' => null];
    }

    abstract protected function label(): string;

    // النواقص الإلزامية لكل المنصات
    public function validate(Property $property): array
    {
        $missing = [];
        $today   = now(config('app.business_timezone'))->toDateString();

        if (!$property->license_number)     $missing[] = 'رقم رخصة الإعلان';
        if (!$property->ad_license_expiry)  $missing[] = 'تاريخ انتهاء رخصة الإعلان';
        elseif ($property->ad_license_expiry->toDateString() < $today) $missing[] = 'رخصة الإعلان منتهية';
        if (!$property->fal_license_number) $missing[] = 'رقم رخصة فال';
        if ((float) $property->listed_price <= 0) $missing[] = 'السعر';
        if ((float) $property->total_area <= 0) $missing[] = 'المساحة كرقم (مثل 565,56)';
        if (!$property->property_type_id)   $missing[] = 'نوع العقار';
        if (!$property->listing_purpose)    $missing[] = 'بيع أو إيجار';
        if (!$property->city_id)            $missing[] = 'المدينة';
        if (!$property->neighborhood_id)    $missing[] = 'الحي';
        if ($property->latitude === null || $property->longitude === null || $property->latitude === '' || $property->longitude === '') {
            $missing[] = 'الإحداثيات';
        }
        $images = $property->images()->count();
        if ($images < 3) $missing[] = "3 صور على الأقل (موجود {$images})";

        return $missing;
    }
}
