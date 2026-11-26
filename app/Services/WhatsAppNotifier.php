<?php

namespace App\Services;

use App\Http\Controllers\API\BroadcastController;
use App\Models\Employee;
use App\Models\NotificationLog;
use App\Models\WaGatewaySetting;
use App\Models\WaOutbox;
use Illuminate\Support\Facades\Log;

// نسخة واتساب من إشعارات النظام: بتنحط بطابور wa_outbox وبيبعتها برنامج المكتب
class WhatsAppNotifier
{
    public const PURPOSE = 'notification';
    private const EXPIRE_HOURS = 12;   // إذا برنامج المكتب مطفي أكتر من هيك، الإشعار القديم ما بيوصل

    public static function queue(NotificationLog $n): void
    {
        try {
            if (!WaGatewaySetting::current()->notify_whatsapp) return;

            $employee = Employee::find($n->employee_id, ['id', 'phone', 'whatsapp', 'status']);
            if (!$employee || $employee->status !== 'نشط') return;

            $phone = BroadcastController::normalizePhone($employee->whatsapp ?: $employee->phone);
            if (strlen($phone) < 11) return;

            $base = rtrim(config('app.frontend_url', 'https://system.makanasa.com'), '/');
            $link = $n->url ? $base . '/' . ltrim($n->url, '/') : $base . '/notifications';

            WaOutbox::create([
                'phone'      => $phone,
                'purpose'    => self::PURPOSE,
                'message'    => "🔔 *{$n->title}*\n{$n->body}\n\n{$link}",
                'status'     => 'pending',
                'expires_at' => now()->addHours(self::EXPIRE_HOURS),
            ]);
        } catch (\Throwable $e) {
            // الإشعار بالنظام أهم — فشل الواتساب ما لازم يوقف شي
            Log::warning('WhatsApp notification queue failed: ' . $e->getMessage());
        }
    }
}
