<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Publishing\PublicationService;
use Illuminate\Console\Command;

// يومياً: تنبيه الناشر والأدمن برخص الإعلان المنتهية أو اللي بتنتهي خلال 7 أيام
class PublishingLicenseAlerts extends Command
{
    protected $signature = 'publishing:license-alerts';
    protected $description = 'Notify publishers about expired or soon-expiring ad licenses';

    public function handle(PublicationService $service): int
    {
        $today = now(config('app.business_timezone'))->toDateString();
        $soon  = now(config('app.business_timezone'))->addDays(7)->toDateString();

        $properties = Property::whereIn('workflow_status', ['ready_for_publish', 'in_publishing'])
            ->whereNotNull('ad_license_expiry')->whereDate('ad_license_expiry', '<=', $soon)
            ->get(['id', 'ad_license_expiry']);

        if ($properties->isEmpty()) return self::SUCCESS;

        $expired = $properties->filter(fn($p) => $p->ad_license_expiry->toDateString() < $today)->count();
        $expiring = $properties->count() - $expired;

        $service->notifyPublishers(
            'تنبيه رخص الإعلان',
            "منتهية: {$expired} — تنتهي خلال 7 أيام: {$expiring}",
            'تحذير',
            '/publishing'
        );

        return self::SUCCESS;
    }
}
