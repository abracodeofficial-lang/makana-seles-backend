<?php

namespace App\Http\Controllers\API;

use App\Models\Property;
use App\Models\PropertyPublication;
use App\Models\PublishingSetting;
use App\Services\Publishing\BayutFeed;
use App\Services\Publishing\ListingPayload;
use App\Services\Publishing\Platforms\AqarManualPublisher;
use App\Services\Publishing\PublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// موديول النشر — للناشر والأدمن (Gates بـ AppServiceProvider)
class PublishingController extends BaseController
{
    public function __construct(private PublicationService $service) {}

    private function gate(string $ability): void
    {
        abort_unless(Gate::allows($ability), 403, 'ليس لديك صلاحية لهذا الإجراء');
    }

    // GET /api/publishing?tab=ready|published|needs_update|failed|all&search=&page=
    public function index(Request $request): JsonResponse
    {
        $this->gate('publishing.view');

        $byStatus = fn($q, $status) => $q->whereHas('publications', fn($p) => $p->where('status', $status));
        $tabs = [
            'ready'        => fn($q) => $q->where('workflow_status', 'ready_for_publish'),
            'published'    => fn($q) => $byStatus($q, PropertyPublication::PUBLISHED),
            'needs_update' => fn($q) => $byStatus($q, PropertyPublication::NEEDS_UPDATE),
            'failed'       => fn($q) => $byStatus($q, PropertyPublication::FAILED),
            'all'          => fn($q) => $q->whereIn('workflow_status', ['ready_for_publish', 'in_publishing', 'returned']),
        ];

        $base = function () use ($request) {
            $q = Property::query();
            if ($s = $request->search) {
                $q->where(fn($w) => $w->where('name', 'like', "%$s%")->orWhere('property_code', 'like', "%$s%")->orWhere('license_number', 'like', "%$s%"));
            }
            return $q;
        };

        $tab   = $tabs[$request->tab] ?? $tabs['ready'];
        $page  = $tab($base())->with(['publications', 'images', 'createdBy:id,full_name'])
            ->latest('ready_at')->latest('id')->paginate(15);

        $rows = $page->getCollection()->map(fn($p) => [
            'id'              => $p->id,
            'name'            => $p->name,
            'property_code'   => $p->property_code,
            'license_number'  => $p->license_number,
            'workflow_status' => $p->workflow_status,
            'ready_at'        => $p->ready_at,
            'entered_by'      => $p->createdBy?->full_name,
            'thumbnail'       => $p->images->first()?->url,
            'statuses'        => $p->publications->pluck('status', 'platform'),
        ]);

        return response()->json([
            'status'       => true,
            'data'         => $rows,
            'counts'       => collect($tabs)->map(fn($t) => $t($base())->count()),
            'platforms'    => $this->platformList(),
            'alerts'       => $this->licenseAlerts(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
        ]);
    }

    // GET /api/publishing/properties/{id}
    public function show(int $id): JsonResponse
    {
        $this->gate('publishing.view');

        $property = Property::with(['propertyType:id,name', 'city:id,name', 'neighborhood:id,name', 'images', 'createdBy:id,full_name'])->findOrFail($id);
        $pubs     = PropertyPublication::where('property_id', $id)->get()->keyBy('platform');

        $platforms = collect($this->service->platforms())->map(function ($platform, $key) use ($property, $pubs) {
            $pub = $pubs->get($key);
            return $platform->describe() + [
                'missing'      => $platform->validate($property),
                'status'       => $pub?->status,
                'external_url' => $pub?->external_url,
                'error'        => $pub?->error_message,
                'published_at' => $pub?->published_at,
            ];
        })->values();

        $logs = \App\Models\PublicationLog::whereIn('publication_id', $pubs->pluck('id'))
            ->with(['user:id,full_name', 'publication:id,platform'])->latest('id')->limit(50)->get()
            ->map(fn($l) => [
                'id' => $l->id, 'platform' => $l->publication?->platform, 'action' => $l->action, 'status' => $l->status,
                'request' => $l->request_summary, 'response' => $l->response_summary,
                'user' => $l->user?->full_name, 'created_at' => $l->created_at,
            ]);

        return $this->success([
            'property'  => $property->only(['id', 'name', 'property_code', 'listed_price', 'total_area', 'total_area_text', 'listing_purpose', 'license_number', 'ad_license_expiry', 'fal_license_number', 'workflow_status', 'return_note', 'latitude', 'longitude', 'description'])
                + ['type' => $property->propertyType?->name, 'city' => $property->city?->name, 'neighborhood' => $property->neighborhood?->name,
                   'entered_by' => $property->createdBy?->full_name, 'images' => $property->images],
            'platforms' => $platforms,
            'logs'      => $logs,
        ]);
    }

    // POST /api/publishing/properties/{id}/publish  { platforms: [...] }
    public function publish(Request $request, int $id): JsonResponse
    {
        $this->gate('publishing.act');
        $data = $request->validate(['platforms' => 'required|array|min:1', 'platforms.*' => 'string']);

        $results = $this->service->dispatch(Property::findOrFail($id), $data['platforms'], $request->user()->id);
        return $this->success($results, 'تم إرسال الطلب');
    }

    // POST /api/publishing/properties/{id}/unpublish  { platforms: [...] }
    public function unpublish(Request $request, int $id): JsonResponse
    {
        $this->gate('publishing.act');
        $data = $request->validate(['platforms' => 'required|array|min:1', 'platforms.*' => 'string']);

        Property::findOrFail($id);
        $live = PropertyPublication::where('property_id', $id)->whereIn('platform', $data['platforms'])
            ->whereIn('status', [PropertyPublication::PUBLISHED, PropertyPublication::NEEDS_UPDATE, PropertyPublication::FAILED, PropertyPublication::PENDING])->get();

        foreach ($live as $publication) $this->service->enqueue($publication, 'unpublish', $request->user()->id);

        return $this->success(['count' => $live->count()], 'تم إرسال طلب الإيقاف');
    }

    // POST /api/publishing/properties/{id}/return  { note }
    public function returnToEntry(Request $request, int $id): JsonResponse
    {
        $this->gate('publishing.act');
        $data = $request->validate(['note' => 'required|string|max:2000'], ['note.required' => 'لازم تكتب ملاحظة للإرجاع']);

        $property = Property::findOrFail($id);
        Property::whereKey($id)->update(['workflow_status' => 'returned', 'return_note' => $data['note']]);

        foreach (array_unique(array_filter([$property->created_by, $property->ready_by])) as $employeeId) {
            \App\Models\NotificationLog::send($employeeId, 'عقار رجع للتعديل', "{$property->name}: {$data['note']}", 'تحذير', "/properties?open={$id}");
        }

        return $this->success(null, 'تم إرجاع العقار لمدخل البيانات');
    }

    // POST /api/publishing/properties/{id}/confirm/{platform}  { url } — تأكيد النشر اليدوي
    public function confirmManual(Request $request, int $id, string $platform): JsonResponse
    {
        $this->gate('publishing.act');
        $data = $request->validate(['url' => 'required|url|max:500'], ['url.required' => 'الصق رابط الإعلان', 'url.url' => 'الرابط مش صحيح']);

        $connector = $this->service->platform($platform);
        abort_unless($connector && ($connector->describe()['manual'] ?? false), 422, 'هاي المنصة مش يدوية');

        $property = Property::findOrFail($id);
        $missing  = $connector->validate($property);
        if ($missing) return $this->error('نواقص: ' . implode('، ', $missing), 422);

        $publication = PropertyPublication::firstOrCreate(['property_id' => $id, 'platform' => $platform]);
        $publication->update([
            'status' => PropertyPublication::PUBLISHED, 'external_url' => $data['url'], 'error_message' => null,
            'payload_hash' => ListingPayload::hash($property), 'published_by' => $request->user()->id,
            'published_at' => now(), 'last_synced_at' => now(),
        ]);
        $publication->log('publish', PropertyPublication::PUBLISHED, 'تأكيد نشر يدوي', $data['url'], $request->user()->id);
        Property::whereKey($id)->update(['workflow_status' => 'in_publishing']);

        return $this->success(null, 'تم تسجيل النشر');
    }

    // GET /api/publishing/properties/{id}/pack
    public function pack(int $id): JsonResponse
    {
        $this->gate('publishing.view');
        return $this->success(AqarManualPublisher::pack(Property::findOrFail($id)));
    }

    // GET /api/publishing/properties/{id}/images.zip — صور مرتّبة
    public function imagesZip(int $id)
    {
        $this->gate('publishing.view');
        $property = Property::with('images')->findOrFail($id);
        abort_if($property->images->isEmpty(), 422, 'ما في صور للعقار');
        abort_unless(class_exists(\ZipArchive::class), 500, 'الضغط غير متوفر على السيرفر');

        $path = tempnam(sys_get_temp_dir(), 'pack');
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($property->images as $i => $image) {
            $full = Storage::disk('public')->path($image->file_path);
            if (is_file($full)) {
                $zip->addFile($full, sprintf('%02d-%s.%s', $i + 1, Str::slug($property->property_code), pathinfo($image->file_path, PATHINFO_EXTENSION)));
            }
        }
        $zip->close();

        return response()->download($path, "{$property->property_code}-images.zip", ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    // GET /api/publishing/settings (أدمن)
    public function settings(): JsonResponse
    {
        $this->gate('publishing.admin');
        return $this->success($this->settingsPayload());
    }

    // PUT /api/publishing/settings (أدمن)
    public function updateSettings(Request $request): JsonResponse
    {
        $this->gate('publishing.admin');
        $data = $request->validate([
            'public_base_url'        => 'nullable|url|starts_with:https://',
            'wordpress_base_url'     => 'nullable|url|starts_with:https://',
            'wordpress_username'     => 'nullable|string|max:255',
            'wordpress_app_password' => 'nullable|string|max:255',
            'wordpress_mock'         => 'boolean',
        ], ['*.starts_with' => 'الرابط لازم يبلّش بـ https://']);

        // كلمة السر الفاضية معناها "خليها متل ما هي"
        if (empty($data['wordpress_app_password'])) unset($data['wordpress_app_password']);

        PublishingSetting::current()->update($data);
        return $this->success($this->settingsPayload(), 'تم حفظ الإعدادات');
    }

    // POST /api/publishing/settings/test-wordpress (أدمن) — بيفحص الإعدادات المحفوظة مع إضافة Makana Connector
    public function testWordpress(): JsonResponse
    {
        $this->gate('publishing.admin');
        $s = PublishingSetting::current();
        if (!$s->wordpress_base_url || !$s->wordpress_username || !$s->wordpress_app_password) {
            return $this->error('عبّي رابط الموقع واسم المستخدم والـ Application Password واحفظ أول', 422);
        }

        try {
            $info = app(\App\Services\Publishing\Platforms\WordPressPublisher::class)->ping();
        } catch (\App\Services\Publishing\PublishException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'rest_no_route') || str_contains($msg, ' 404')) $msg = 'الموقع رد، بس إضافة Makana Connector مش مركّبة أو مش مفعّلة';
            elseif (str_contains($msg, ' 401') || str_contains($msg, ' 403')) $msg = 'اسم المستخدم أو الـ Application Password غلط، أو المستخدم ما عنده صلاحية محرر/مدير';
            return $this->error($msg, 422);
        }

        if (empty($info['ok'])) return $this->error('الموقع رد بشي غير متوقع', 422);
        return $this->success($info, empty($info['mylisting']) ? 'الاتصال تمام بس قالب MyListing مش مفعّل' : 'الاتصال شغال ✓');
    }

    // POST /api/publishing/settings/regenerate-feed-token (أدمن)
    public function regenerateFeedToken(): JsonResponse
    {
        $this->gate('publishing.admin');
        PublishingSetting::current()->update(['bayut_token' => Str::random(48)]);
        return $this->success($this->settingsPayload(), 'تم تغيير التوكن — الرابط القديم وقف');
    }

    private function settingsPayload(): array
    {
        $s = PublishingSetting::current();
        return [
            'public_base_url'    => $s->public_base_url,
            'wordpress_base_url' => $s->wordpress_base_url,
            'wordpress_username' => $s->wordpress_username,
            'has_password'       => (bool) $s->wordpress_app_password,
            'wordpress_mock'     => $s->wordpress_mock,
            'bayut_feed_url'     => $s->bayutFeedUrl(),
        ];
    }

    private function platformList(): array
    {
        return collect($this->service->platforms())->map(fn($p) => $p->describe())->values()->all();
    }

    private function licenseAlerts(): array
    {
        $today = now(config('app.business_timezone'))->toDateString();
        $soon  = now(config('app.business_timezone'))->addDays(7)->toDateString();

        return Property::whereIn('workflow_status', ['ready_for_publish', 'in_publishing'])
            ->whereNotNull('ad_license_expiry')->whereDate('ad_license_expiry', '<=', $soon)
            ->orderBy('ad_license_expiry')->limit(50)->get(['id', 'name', 'property_code', 'ad_license_expiry'])
            ->map(fn($p) => [
                'id' => $p->id, 'name' => $p->name, 'property_code' => $p->property_code,
                'expiry' => $p->ad_license_expiry->toDateString(),
                'expired' => $p->ad_license_expiry->toDateString() < $today,
            ])->all();
    }
}
