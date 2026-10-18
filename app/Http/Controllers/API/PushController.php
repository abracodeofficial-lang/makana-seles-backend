<?php

namespace App\Http\Controllers\API;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

// اشتراك الجهاز بإشعارات Web Push
class PushController extends BaseController
{
    // المفتاح العام — عام بطبيعته (المتصفح بيحتاجه ليشترك)
    public function publicKey(): JsonResponse
    {
        return $this->success([
            'public_key'         => WebPushService::keys()['publicKey'],
            'scheduler_last_run' => Cache::get('scheduler:last_run'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'    => 'required|url|max:500',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'employee_id' => $request->user()->id,
                'p256dh'      => $data['keys']['p256dh'],
                'auth'        => $data['keys']['auth'],
                'user_agent'  => substr((string) $request->userAgent(), 0, 255),
            ]
        );

        return $this->success(null, 'تم تفعيل الإشعارات على هذا الجهاز');
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);
        PushSubscription::where('employee_id', $request->user()->id)
            ->where('endpoint', $request->endpoint)->delete();

        return $this->success(null, 'تم إيقاف الإشعارات على هذا الجهاز');
    }

    // إشعار تجريبي للموظف نفسه — للتأكد إن الجهاز بيستقبل
    public function test(Request $request): JsonResponse
    {
        $sent = WebPushService::sendToEmployee(
            $request->user()->id,
            '🔔 الإشعارات شغالة',
            'هيك رح توصلك تذكيرات بداية ونهاية الدوام على هذا الجهاز',
            '/punch',
            'test'
        );

        return $sent > 0
            ? $this->success(['sent' => $sent], 'تم إرسال إشعار تجريبي')
            : $this->error('ما في جهاز مفعّل عليه الإشعارات لهذا الحساب', 422);
    }
}
