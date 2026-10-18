<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

// إرسال إشعارات للجهاز (تظهر حتى لو تبويب النظام مسكّر)
class WebPushService
{
    private const KEYS_FILE = 'webpush/vapid.json';

    // مفاتيح VAPID: من .env إذا موجودة، وإلا بتنولّد مرة وحدة وبتنحفظ بـ storage
    // (storage ما بينمسح مع النشر، فالمفاتيح بتضل ثابتة والاشتراكات بتضل شغالة)
    public static function keys(): array
    {
        if (env('VAPID_PUBLIC_KEY') && env('VAPID_PRIVATE_KEY')) {
            return ['publicKey' => env('VAPID_PUBLIC_KEY'), 'privateKey' => env('VAPID_PRIVATE_KEY')];
        }

        $disk = Storage::disk('local');
        if ($disk->exists(self::KEYS_FILE)) {
            return json_decode($disk->get(self::KEYS_FILE), true);
        }

        $keys = VAPID::createVapidKeys();
        $disk->put(self::KEYS_FILE, json_encode($keys));
        return $keys;
    }

    public static function sendToEmployee(int $employeeId, string $title, string $body, ?string $url = null, ?string $tag = null): int
    {
        $subs = PushSubscription::where('employee_id', $employeeId)->get();
        if ($subs->isEmpty()) return 0;

        try {
            $keys    = self::keys();
            $webPush = new WebPush(['VAPID' => [
                'subject'    => config('app.url') ?: 'mailto:admin@makanasa.com',
                'publicKey'  => $keys['publicKey'],
                'privateKey' => $keys['privateKey'],
            ]], ['TTL' => 3600]);

            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
                'url'   => $url ?? '/',
                'tag'   => $tag,
            ], JSON_UNESCAPED_UNICODE);

            foreach ($subs as $sub) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth],
                ]), $payload);
            }

            $sent = 0;
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                } elseif ($report->isSubscriptionExpired()) {
                    // المتصفح ألغى الاشتراك (مسح بيانات/ألغى الإذن) — منشيله
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                } else {
                    Log::warning('WebPush failed: ' . $report->getReason());
                }
            }
            return $sent;
        } catch (\Throwable $e) {
            Log::error('WebPush error: ' . $e->getMessage());
            return 0;
        }
    }
}
