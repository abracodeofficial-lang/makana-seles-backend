<?php

namespace App\Services\Publishing;

use App\Models\Property;
use App\Models\PropertyPublication;

// كل منصة نشر كلاس بمجلد Platforms يطبّق هاد العقد — الواجهة بتتبنى من describe() لحالها
interface PlatformPublisher
{
    public function key(): string;

    /** @return array{key:string,label:string,manual:bool,notice:?string} */
    public function describe(): array;

    /** @return string[] قائمة النواقص (فاضية = جاهز للنشر) */
    public function validate(Property $property): array;

    public function publish(Property $property, PropertyPublication $publication): PublishResult;
    public function update(Property $property, PropertyPublication $publication): PublishResult;
    public function unpublish(Property $property, PropertyPublication $publication): PublishResult;
}
