<?php

namespace App\Http\Controllers\API;

use App\Models\ContactList;
use App\Models\ContactListMember;
use App\Models\Lead;
use App\Models\Owner;
use App\Models\WaCampaign;
use App\Models\WaCampaignRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// رسائل واتساب جماعية: اختيار المستلمين (بنفس فلاتر صفحتي الملاك/المهتمين)،
// قوائم أرقام محفوظة، وحملات بتتبع مين انبعتله ومين لسا
class BroadcastController extends BaseController
{
    private const AUDIENCES = ['owners', 'leads'];
    private const MAX_RECIPIENTS = 2000;

    // الصلاحية: عرض صفحة الملاك أو المهتمين حسب القسم
    private function authorizeAudience(Request $request, ?string $audience): string
    {
        abort_unless(in_array($audience, self::AUDIENCES, true), 422, 'القسم غير صحيح');
        abort_unless($request->user()->hasPermission($audience, 'view'), 403, 'ليس لديك صلاحية');
        return $audience;
    }

    // query مفلترة بنفس فلاتر الصفحة الأصلية + (اختياري) أعضاء قائمة محفوظة
    private function contactsQuery(string $audience, Request $request)
    {
        $query = $audience === 'owners'
            ? app(OwnerController::class)->applyFilters(Owner::query(), $request)
            : app(LeadController::class)->applyFilters(Lead::query(), $request);

        $table = $audience === 'owners' ? 'owners' : 'leads';

        if ($request->list_id) {
            $query->whereIn("$table.id", ContactListMember::where('contact_list_id', $request->list_id)->select('contact_id'));
        }
        if ($request->ids) {
            $query->whereIn("$table.id", array_map('intval', (array) $request->ids));
        }

        return $query;
    }

    private function mapContact(string $audience, $c): array
    {
        return $audience === 'owners'
            ? ['id' => $c->id, 'name' => $c->name, 'code' => $c->owner_code, 'phone' => $c->whatsapp ?: $c->phone, 'city' => $c->city?->name]
            : ['id' => $c->id, 'name' => $c->name, 'code' => $c->lead_code,  'phone' => $c->phone, 'city' => $c->city?->name];
    }

    // GET /broadcast/recipients?audience=owners&...filters
    public function recipients(Request $request): JsonResponse
    {
        $audience = $this->authorizeAudience($request, $request->audience);
        $query    = $this->contactsQuery($audience, $request)->with('city:id,name')->latest('id');
        $total    = $query->clone()->count();

        $items = $query->limit(self::MAX_RECIPIENTS)->get()
            ->map(fn ($c) => $this->mapContact($audience, $c))->values();

        return $this->success(['items' => $items, 'total' => $total, 'limit' => self::MAX_RECIPIENTS]);
    }

    // ── القوائم المحفوظة ────────────────────────────────────
    public function lists(Request $request): JsonResponse
    {
        $audience = $this->authorizeAudience($request, $request->audience);

        return $this->success(
            ContactList::where('audience', $audience)->withCount('members')
                ->with('createdBy:id,full_name')->latest()->get()
        );
    }

    public function storeList(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'audience'      => 'required|in:owners,leads',
            'contact_ids'   => 'required|array|min:1',
            'contact_ids.*' => 'integer',
        ]);
        $this->authorizeAudience($request, $data['audience']);

        $list = DB::transaction(function () use ($data, $request) {
            $list = ContactList::create([
                'name' => $data['name'], 'audience' => $data['audience'], 'created_by' => $request->user()->id,
            ]);
            $this->syncMembers($list, $data['contact_ids']);
            return $list;
        });

        return $this->success($list->loadCount('members'), 'تم حفظ القائمة', 201);
    }

    // تعديل الاسم و/أو إضافة/إزالة أعضاء
    public function updateList(Request $request, ContactList $list): JsonResponse
    {
        $this->authorizeAudience($request, $list->audience);
        $data = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'add_ids'      => 'sometimes|array',
            'add_ids.*'    => 'integer',
            'remove_ids'   => 'sometimes|array',
            'remove_ids.*' => 'integer',
        ]);

        if (isset($data['name'])) $list->update(['name' => $data['name']]);
        if (!empty($data['add_ids'])) $this->syncMembers($list, $data['add_ids']);
        if (!empty($data['remove_ids'])) {
            $list->members()->whereIn('contact_id', $data['remove_ids'])->delete();
        }

        return $this->success($list->loadCount('members'), 'تم تحديث القائمة');
    }

    public function destroyList(Request $request, ContactList $list): JsonResponse
    {
        $this->authorizeAudience($request, $list->audience);
        $list->delete();
        return $this->success(null, 'تم حذف القائمة');
    }

    private function syncMembers(ContactList $list, array $ids): void
    {
        $now  = now();
        $rows = collect($ids)->unique()->map(fn ($id) => [
            'contact_list_id' => $list->id, 'contact_id' => (int) $id, 'created_at' => $now, 'updated_at' => $now,
        ])->all();
        ContactListMember::insertOrIgnore($rows);
    }

    // ── الحملات ─────────────────────────────────────────────
    public function campaigns(Request $request): JsonResponse
    {
        $audiences = array_values(array_filter(self::AUDIENCES, fn ($a) => $request->user()->hasPermission($a, 'view')));

        $campaigns = WaCampaign::whereIn('audience', $audiences)
            ->when($request->audience, fn ($q) => $q->where('audience', $request->audience))
            ->withCount([
                'recipients',
                'recipients as sent_count'    => fn ($q) => $q->where('status', 'sent'),
                'recipients as skipped_count' => fn ($q) => $q->where('status', 'skipped'),
            ])
            ->with('createdBy:id,full_name')
            ->latest()->limit(100)->get();

        return $this->success($campaigns);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'nullable|string|max:150',
            'audience'      => 'required|in:owners,leads',
            'message'       => 'required|string|max:4000',
            'contact_ids'   => 'required|array|min:1|max:' . self::MAX_RECIPIENTS,
            'contact_ids.*' => 'integer',
            'min_delay'     => 'nullable|integer|min:5|max:3600',
            'max_delay'     => 'nullable|integer|min:5|max:3600',
        ]);
        $audience = $this->authorizeAudience($request, $data['audience']);

        // لقطة من الأسماء والأرقام وقت الإنشاء — بدون أرقام فاضية وبدون تكرار نفس الرقم
        $contacts = $this->contactsQuery($audience, new Request(['ids' => $data['contact_ids']]))->get()
            ->map(fn ($c) => $this->mapContact($audience, $c))
            ->filter(fn ($c) => self::normalizePhone($c['phone']) !== '')
            ->unique(fn ($c) => self::normalizePhone($c['phone']))
            ->values();

        if ($contacts->isEmpty()) return $this->error('ما في أرقام صالحة بالمستلمين المختارين', 422);

        $min = $data['min_delay'] ?? 30;
        $max = max($min, $data['max_delay'] ?? 90);

        $campaign = DB::transaction(function () use ($data, $request, $contacts, $min, $max, $audience) {
            $campaign = WaCampaign::create([
                'title'      => ($data['title'] ?? null) ?: ('حملة ' . ($audience === 'owners' ? 'ملاك' : 'مهتمين') . ' — ' . now(config('app.business_timezone'))->format('Y-m-d H:i')),
                'audience'   => $audience,
                'message'    => $data['message'],
                'min_delay'  => $min,
                'max_delay'  => $max,
                'created_by' => $request->user()->id,
            ]);
            $now = now();
            WaCampaignRecipient::insert($contacts->map(fn ($c) => [
                'wa_campaign_id' => $campaign->id, 'contact_id' => $c['id'], 'name' => $c['name'] ?? '',
                'phone' => $c['phone'], 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
            ])->all());
            return $campaign;
        });

        return $this->success($this->campaignPayload($campaign), 'تم إنشاء الحملة', 201);
    }

    public function showCampaign(Request $request, WaCampaign $campaign): JsonResponse
    {
        $this->authorizeAudience($request, $campaign->audience);
        return $this->success($this->campaignPayload($campaign));
    }

    public function updateRecipient(Request $request, WaCampaign $campaign, WaCampaignRecipient $recipient): JsonResponse
    {
        $this->authorizeAudience($request, $campaign->audience);
        abort_unless($recipient->wa_campaign_id === $campaign->id, 404);

        $data = $request->validate(['status' => 'required|in:pending,sent,skipped']);
        $recipient->update([
            'status'  => $data['status'],
            'sent_at' => $data['status'] === 'sent' ? now() : null,
            'sent_by' => $data['status'] === 'sent' ? $request->user()->id : null,
        ]);

        return $this->success($recipient);
    }

    public function destroyCampaign(Request $request, WaCampaign $campaign): JsonResponse
    {
        $this->authorizeAudience($request, $campaign->audience);
        $campaign->delete();
        return $this->success(null, 'تم حذف الحملة');
    }

    // 0501234567 / 501234567 / 00966... / +966... → 966501234567 (نفس منطق waPhone بالفرونت)
    public static function normalizePhone(?string $phone): string
    {
        $d = preg_replace('/\D/', '', (string) $phone);
        if ($d === '') return '';
        if (str_starts_with($d, '00'))  $d = substr($d, 2);
        if (str_starts_with($d, '966')) return $d;
        if (str_starts_with($d, '0'))   return '966' . substr($d, 1);
        return '966' . $d;
    }

    private function campaignPayload(WaCampaign $campaign): array
    {
        return array_merge($campaign->load('createdBy:id,full_name')->toArray(), [
            'recipients' => $campaign->recipients()->orderBy('id')->get(),
        ]);
    }
}
