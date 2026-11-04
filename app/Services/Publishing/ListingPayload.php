<?php

namespace App\Services\Publishing;

use App\Models\Property;

// بيانات الإعلان الموحّدة — نفسها بتتبعت للمنصات وبيتحسب منها payload_hash لكشف التعديلات
class ListingPayload
{
    public static function build(Property $p): array
    {
        $p->loadMissing(['propertyType:id,name', 'city:id,name', 'neighborhood:id,name']);

        $text = fn($v) => trim(strip_tags(html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8')));

        return [
            'uuid'              => $p->uuid,
            'code'              => $p->property_code,
            'title'             => $p->name,
            'description'       => (string) $p->description,
            'features'          => (string) $p->features,
            'components'        => (string) $p->components,
            'description_text'  => $text($p->description),
            'purpose'           => $p->listing_purpose,
            'type'              => $p->propertyType?->name,
            'price'             => (float) $p->listed_price,
            'area'              => (float) $p->total_area,
            'city'              => $p->city?->name,
            'neighborhood'      => $p->neighborhood?->name,
            'latitude'          => $p->latitude === null ? null : (float) $p->latitude,
            'longitude'         => $p->longitude === null ? null : (float) $p->longitude,
            'direction'         => $p->direction,
            'facade'            => $p->facade,
            'street_width'      => (float) $p->street_width,
            'rooms'             => (int) $p->rooms_count,
            'bathrooms'         => (int) $p->bathrooms_count,
            'halls'             => (int) $p->halls_count,
            'floors'            => (int) $p->floors_count,
            'ad_license_number' => $p->license_number,
            'ad_license_expiry' => $p->ad_license_expiry?->toDateString(),
            'fal_license_number'=> $p->fal_license_number,
            'images'            => $p->images()->orderBy('sort_order')->orderBy('id')->get()->pluck('url')->all(),
        ];
    }

    public static function hash(Property $p): string
    {
        return hash('sha256', json_encode(self::build($p), JSON_UNESCAPED_UNICODE));
    }
}
