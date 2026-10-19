<?php

namespace App\Http\Controllers\API;

use App\Models\WaCampaign;
use App\Models\WaCampaignRecipient;
use App\Models\WaGatewaySetting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// ربط الواتساب عبر برنامج على كمبيوتر المكتب (tools/wa-gateway).
// البرنامج هو اللي بيسأل السيرفر كل كم ثانية (ما في داعي نفتح منافذ بالمكتب):
//   heartbeat → حالة الاتصال + QR،  next → الرسالة التالية،  report → نتيجة الإرسال.
// السيرفر هو اللي بيقرر التوقيت: فاصل عشوائي لكل حملة + حد يومي + ساعات عمل.
class WaGatewayController extends BaseController
{
    private const STATE_KEY   = 'wa-gateway:state';
    private const COMMAND_KEY = 'wa-gateway:command';
    private const ONLINE_SECONDS  = 60;   // بدون نبضة أكتر من هيك = الجهاز مطفي/مفصول
    private const STALE_SENDING_MINUTES = 5;

    private function tz(): string { return config('app.business_timezone', 'Asia/Riyadh'); }

    // ════════ نقاط البرنامج (بمفتاح الجهاز) ════════
    private function authorizeDevice(Request $request): WaGatewaySetting
    {
        $settings = WaGatewaySetting::current();
        abort_unless(hash_equals($settings->token, (string) $request->header('X-Gateway-Token')), 401, 'مفتاح الجهاز غير صحيح');
        return $settings;
    }

    // POST /wa-gateway/heartbeat  {status: connecting|qr|connected, qr?, phone?, name?}
    public function heartbeat(Request $request): JsonResponse
    {
        $this->authorizeDevice($request);
        $data = $request->validate([
            'status' => 'required|in:connecting,qr,connected,logged_out',
            'qr'     => 'nullable|string|max:2000',
            'phone'  => 'nullable|string|max:30',
            'name'   => 'nullable|string|max:100',
            'version'=> 'nullable|string|max:20',
        ]);

        Cache::put(self::STATE_KEY, array_merge($data, ['last_seen' => now()->toIso8601String()]), now()->addDay());

        // أوامر من الواجهة (مثلاً فصل الرقم) — بتنبعت مرة وحدة
        $command = Cache::pull(self::COMMAND_KEY);
        return response()->json(['status' => true, 'command' => $command]);
    }

    // GET /wa-gateway/next — الرسالة التالية إذا حان وقتها، وإلا null مع السبب
    public function next(Request $request): JsonResponse
    {
        $settings = $this->authorizeDevice($request);
        $now = Carbon::now($this->tz());

        if ($settings->paused) return $this->idle('الإرسال موقوف من الإعدادات');

        $start = Carbon::parse($now->toDateString() . ' ' . $settings->work_start, $this->tz());
        $end   = Carbon::parse($now->toDateString() . ' ' . $settings->work_end, $this->tz());
        if ($now->lt($start) || $now->gte($end)) return $this->idle('خارج ساعات الإرسال');

        if ($this->sentToday() >= $settings->daily_limit) return $this->idle('وصلنا الحد اليومي');

        // رسائل علقت بحالة "جاري" (البرنامج انقطع بنصها) → بترجع للانتظار
        WaCampaignRecipient::where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_SENDING_MINUTES))
            ->update(['status' => 'pending']);

        $job = DB::transaction(function () {
            $campaign = WaCampaign::where('auto_status', 'running')
                ->where(fn ($q) => $q->whereNull('next_send_at')->orWhere('next_send_at', '<=', now()))
                ->orderBy('next_send_at')->orderBy('id')
                ->lockForUpdate()->first();
            if (!$campaign) return null;

            $recipient = $campaign->recipients()->where('status', 'pending')->orderBy('id')->lockForUpdate()->first();
            if (!$recipient) {
                // ما ضل حدا: إذا في رسالة لسا عم تنبعت منستنى، وإلا الحملة خلصت
                if (!$campaign->recipients()->where('status', 'sending')->exists()) {
                    $campaign->update(['auto_status' => 'done']);
                }
                return null;
            }

            $recipient->update(['status' => 'sending', 'via' => 'auto', 'error' => null]);
            $min = $campaign->min_delay;
            $max = max($min, $campaign->max_delay);
            $campaign->update(['next_send_at' => now()->addSeconds(random_int($min, $max))]);

            return [
                'id'    => $recipient->id,
                'phone' => BroadcastController::normalizePhone($recipient->phone),
                'text'  => BroadcastController::fillMessage($campaign->message, $recipient->name),
            ];
        });

        return response()->json(['status' => true, 'job' => $job, 'reason' => $job ? null : 'لا يوجد رسائل مستحقة الآن']);
    }

    // POST /wa-gateway/report {id, ok, error?}
    public function report(Request $request): JsonResponse
    {
        $this->authorizeDevice($request);
        $data = $request->validate([
            'id'    => 'required|integer',
            'ok'    => 'required|boolean',
            'error' => 'nullable|string|max:255',
        ]);

        $recipient = WaCampaignRecipient::findOrFail($data['id']);
        $recipient->update($data['ok']
            ? ['status' => 'sent', 'sent_at' => now(), 'error' => null, 'via' => 'auto']
            : ['status' => 'failed', 'error' => $data['error'] ?: 'تعذّر الإرسال']);

        return response()->json(['status' => true]);
    }

    private function idle(string $reason): JsonResponse
    {
        return response()->json(['status' => true, 'job' => null, 'reason' => $reason]);
    }

    private function sentToday(): int
    {
        $startUtc = Carbon::now($this->tz())->startOfDay()->utc();
        return WaCampaignRecipient::where('status', 'sent')->where('via', 'auto')
            ->where('sent_at', '>=', $startUtc)->count();
    }

    // ════════ نقاط الواجهة (Sanctum) ════════
    private function canManage(Request $request): bool
    {
        return $request->user()->hasPermission('settings', 'edit');
    }

    // GET /broadcast/gateway
    public function show(Request $request): JsonResponse
    {
        $settings = WaGatewaySetting::current();
        $state    = Cache::get(self::STATE_KEY, []);
        $lastSeen = isset($state['last_seen']) ? Carbon::parse($state['last_seen']) : null;
        $online   = $lastSeen && $lastSeen->gt(now()->subSeconds(self::ONLINE_SECONDS));

        return $this->success([
            'online'     => $online,
            'status'     => $online ? ($state['status'] ?? 'connecting') : 'offline',
            'qr'         => $online && ($state['status'] ?? null) === 'qr' ? ($state['qr'] ?? null) : null,
            'phone'      => $state['phone'] ?? null,
            'name'       => $state['name'] ?? null,
            'last_seen'  => $lastSeen?->toIso8601String(),
            'sent_today' => $this->sentToday(),
            'settings'   => $settings->only(['daily_limit', 'work_start', 'work_end', 'paused']),
            'can_manage' => $this->canManage($request),
        ]);
    }

    // PUT /broadcast/gateway/settings
    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403, 'ليس لديك صلاحية');
        $data = $request->validate([
            'daily_limit' => 'sometimes|integer|min:1|max:1000',
            'work_start'  => 'sometimes|date_format:H:i',
            'work_end'    => 'sometimes|date_format:H:i',
            'paused'      => 'sometimes|boolean',
        ]);
        WaGatewaySetting::current()->update($data);
        return $this->success(null, 'تم حفظ الإعدادات');
    }

    // POST /broadcast/gateway/logout — فصل الرقم (بيتنفذ مع النبضة الجاية)
    public function logout(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403, 'ليس لديك صلاحية');
        Cache::put(self::COMMAND_KEY, 'logout', now()->addMinutes(10));
        return $this->success(null, 'رح ينفصل الرقم خلال ثواني');
    }

    // POST /broadcast/gateway/token — مفتاح جديد (البرنامج القديم بيوقف لحد ما ينزل الجديد)
    public function regenerateToken(Request $request): JsonResponse
    {
        abort_unless($this->canManage($request), 403, 'ليس لديك صلاحية');
        WaGatewaySetting::current()->update(['token' => Str::random(48)]);
        Cache::forget(self::STATE_KEY);
        return $this->success(null, 'تم تجديد المفتاح — نزّل البرنامج من جديد');
    }

    // GET /broadcast/gateway/download — البرنامج مضغوط مع ملف إعداد فيه عنوان السيرفر والمفتاح
    public function download(Request $request): BinaryFileResponse
    {
        abort_unless($this->canManage($request), 403, 'ليس لديك صلاحية');

        $source = base_path('tools/wa-gateway');
        abort_unless(is_dir($source), 500, 'ملفات البرنامج غير موجودة على السيرفر');

        $zipPath = storage_path('app/wa-gateway-' . Str::random(8) . '.zip');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            if (preg_match('#^(node_modules|auth|config\.json)#', $relative)) continue;
            $zip->addFile($file->getPathname(), 'makana-whatsapp/' . $relative);
        }
        $zip->addFromString('makana-whatsapp/config.json', json_encode([
            // نفس الدومين اللي نزل منه البرنامج (APP_URL ممكن يكون قديم)، ودايماً https (ورا Cloudflare)
            'server' => 'https://' . $request->getHost(),
            'token'  => WaGatewaySetting::current()->token,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();

        return response()->download($zipPath, 'makana-whatsapp.zip')->deleteFileAfterSend();
    }
}
