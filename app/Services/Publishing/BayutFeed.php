<?php

namespace App\Services\Publishing;

use App\Models\Property;
use App\Models\PropertyPublication;
use Illuminate\Support\Facades\Cache;
use XMLWriter;

// Feed بيوت — الهيكل النهائي بانتظار توثيقهم: عدّل config/publishing_bayut.php بس
class BayutFeed
{
    private const CACHE_KEY = 'publishing:bayut_feed';

    public static function xml(): string
    {
        return Cache::rememberForever(self::CACHE_KEY, fn() => self::build());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function build(): string
    {
        $today = now(config('app.business_timezone'))->toDateString();

        $properties = Property::whereHas('publications', fn($q) => $q
                ->where('platform', 'bayut')->where('status', PropertyPublication::PUBLISHED))
            ->whereNotNull('ad_license_expiry')->whereDate('ad_license_expiry', '>=', $today)
            ->get();

        $config = config('publishing_bayut');

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement($config['root']);

        foreach ($properties as $property) {
            $data = ListingPayload::build($property);
            $xml->startElement($config['item']);

            foreach ($config['fields'] as $element => $source) {
                $xml->writeElement($element, self::scalar($data[$source] ?? ''));
            }

            $xml->startElement($config['images_wrapper']);
            foreach ($data['images'] as $url) $xml->writeElement($config['image_item'], $url);
            $xml->endElement();

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    // XML بيرفض حروف التحكم — بنشيلها حتى يضل الملف صالح مهما كان النص
    private static function scalar(mixed $value): string
    {
        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $value);
    }
}
