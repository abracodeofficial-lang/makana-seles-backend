<?php

namespace App\Services\Publishing\Platforms;

use App\Models\Property;
use App\Models\PropertyPublication;
use App\Services\Publishing\BasePublisher;
use App\Services\Publishing\BayutFeed;
use App\Services\Publishing\PublishResult;

// بيوت بتسحب XML Feed: النشر = دخول الـ Feed، والإيقاف = خروج منه
class BayutFeedPublisher extends BasePublisher
{
    public function key(): string { return 'bayut'; }
    protected function label(): string { return 'بيوت Bayut'; }

    public function publish(Property $property, PropertyPublication $publication): PublishResult
    {
        BayutFeed::forget();
        return PublishResult::published($property->property_code, null, 'إضافة للـ Feed', 'بيظهر عند سحب بيوت التالي');
    }

    public function update(Property $property, PropertyPublication $publication): PublishResult
    {
        BayutFeed::forget();
        return PublishResult::published($property->property_code, null, 'تحديث بالـ Feed', 'بيظهر عند سحب بيوت التالي');
    }

    public function unpublish(Property $property, PropertyPublication $publication): PublishResult
    {
        BayutFeed::forget();
        return PublishResult::unpublished('إخراج من الـ Feed', 'تم');
    }
}
