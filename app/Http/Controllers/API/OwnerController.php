<?php

namespace App\Http\Controllers\API;

use App\Models\{Owner, Property, PropertyType, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerController extends BaseController
{
    private const OWNER_TYPES = ['مالك', 'وكيل', 'وسيط', 'مكتب', 'مطور', 'مشروع'];
    private const DIRECTIONS  = ['شمالية', 'جنوبية', 'شرقية', 'غربية', 'شمالية شرقية', 'شمالية غربية', 'جنوبية شرقية', 'جنوبية غربية'];
    private const PRICE_RANGES = ['lt4' => 'أقل من 4M', '4to10' => 'بين 4M-10M', 'gt10' => 'أعلى من 10M'];

    // كود المالك بيتولّد من آخر 4 أرقام بالواتساب — فالواتساب إجباري
    private const WHATSAPP_MESSAGES = [
        'whatsapp.required' => 'رقم الواتساب مطلوب',
        'whatsapp.regex'    => 'رقم الواتساب لازم يكون فيه 4 أرقام على الأقل',
        'whatsapp.max'      => 'رقم الواتساب طويل',
    ];

    // فئة السعر: أقل من 4 مليون / بين 4 و10 / أعلى من 10
    public static function applyPriceRange($q, ?string $range)
    {
        return match ($range) {
            'lt4'   => $q->where('listed_price', '<', 4_000_000),
            '4to10' => $q->whereBetween('listed_price', [4_000_000, 10_000_000]),
            'gt10'  => $q->where('listed_price', '>', 10_000_000),
            default => $q,
        };
    }

    public function applyFilters($query, Request $request)
    {
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('owner_code', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }
        if ($request->type)             $query->where('type', $request->type);
        if ($request->group)            $query->where('group', $request->group);
        if ($request->exclusive_status) $query->where('exclusive_status', $request->exclusive_status);
        if ($request->city_id)          $query->where('city_id', $request->city_id);
        if ($request->date_from)        $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)          $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->name)             $query->where('name', 'like', "%{$request->name}%");
        if ($request->price_update_from) $query->whereDate('price_update_date', '>=', $request->price_update_from);
        if ($request->price_update_to)   $query->whereDate('price_update_date', '<=', $request->price_update_to);
        if ($request->sales_employee_owners_id) $query->where('sales_employee_owners_id', $request->sales_employee_owners_id);
        if ($request->property_type_id) $query->whereHas('properties', fn($p) => $p->where('property_type_id', $request->property_type_id));
        if ($request->direction)        $query->whereHas('properties', fn($p) => $p->where('direction', $request->direction));
        if ($request->price_min)        $query->whereHas('properties', fn($p) => $p->where('listed_price', '>=', $request->price_min));
        if ($request->price_max)        $query->whereHas('properties', fn($p) => $p->where('listed_price', '<=', $request->price_max));
        if ($request->price_range)      $query->whereHas('properties', fn($p) => self::applyPriceRange($p, $request->price_range));

        return $query;
    }

    // GET /api/owners
    public function index(Request $request): JsonResponse
    {
        $query = $this->applyFilters(
            Owner::with(['city', 'salesEmployeeOwners:id,full_name', 'salesEmployeeLeads:id,full_name'])
                ->withCount('properties'),
            $request
        );

        // إحصائيات أعلى الصفحة — بتتبع نفس الفلاتر المطبّقة على الجدول.
        // كل قسم بوكسات بيتجاهل فلتره الخاص (حتى لما تختار نوع، باقي الأنواع ما بتصير صفر).
        $without    = fn(?string $key) => new Request($key ? $request->except([$key, 'page']) : $request->except('page'));
        $ownersBase = fn(?string $key = null) => $this->applyFilters(Owner::query(), $without($key));
        // عقارات الملاك المطابقين — ومع فلاتر العقار نفسها (النوع/الاتجاه/السعر) على مستوى العقار
        $propsBase  = function (?string $key = null) use ($ownersBase, $without) {
            $r = $without($key);
            $q = Property::whereIn('owner_id', $ownersBase($key)->select('owners.id'));
            if ($r->property_type_id) $q->where('property_type_id', $r->property_type_id);
            if ($r->direction)        $q->where('direction', $r->direction);
            if ($r->price_range)      self::applyPriceRange($q, $r->price_range);
            return $q;
        };

        $stats = [
            'total'               => $ownersBase()->count(),
            'properties_added'    => $propsBase()->count(),
            'properties_accepted' => $propsBase()->where('status', 'متاح')->count(),
            'properties_verified' => $propsBase()->where('is_verified', true)->count(),
        ];

        $ownerTypeCounts = $ownersBase('type')->selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type');
        $byOwnerType = collect(self::OWNER_TYPES)
            ->map(fn($t) => ['label' => $t, 'count' => (int) ($ownerTypeCounts[$t] ?? 0)])->values();

        $typeCounts = $propsBase('property_type_id')->selectRaw('property_type_id, count(*) as count')
            ->whereNotNull('property_type_id')->groupBy('property_type_id')->pluck('count', 'property_type_id');
        $byPropertyType = PropertyType::orderBy('id')->get(['id', 'name', 'is_active'])
            ->filter(fn($t) => $t->is_active || isset($typeCounts[$t->id]))
            ->map(fn($t) => ['id' => $t->id, 'label' => $t->name, 'count' => (int) ($typeCounts[$t->id] ?? 0)])->values();

        $dirCounts = $propsBase('direction')->selectRaw('direction, count(*) as count')
            ->whereNotNull('direction')->groupBy('direction')->pluck('count', 'direction');
        $byDirection = collect(self::DIRECTIONS)
            ->map(fn($d) => ['label' => $d, 'count' => (int) ($dirCounts[$d] ?? 0)])->values();

        $byPrice = collect(self::PRICE_RANGES)
            ->map(fn($label, $key) => ['value' => $key, 'label' => $label,
                'count' => self::applyPriceRange($propsBase('price_range'), $key)->count()])->values();

        return response()->json([
            'status'            => true,
            'stats'             => $stats,
            'by_owner_type'     => $byOwnerType,
            'by_property_type'  => $byPropertyType,
            'by_direction'      => $byDirection,
            'by_price'          => $byPrice,
            'data'              => $query->latest()->paginate(15)->toArray(),
        ]);
    }

    // GET /api/owners/export — كل السجلات المطابقة للفلاتر، بدون تقسيم صفحات
    public function export(Request $request): JsonResponse
    {
        $records = $this->applyFilters(
            Owner::with(['city', 'salesEmployeeOwners:id,full_name'])->withCount('properties'),
            $request
        )->latest()->get();

        return $this->success($records);
    }

    // GET /api/owners/{id}
    public function show(int $id): JsonResponse
    {
        $owner = Owner::with([
            'city', 'properties.propertyType', 'properties.city',
            'salesEmployeeOwners:id,full_name,phone',
            'salesEmployeeLeads:id,full_name,phone',
            'createdBy:id,full_name',
        ])->findOrFail($id);

        return $this->success($owner);
    }

    // POST /api/owners
    // الأرقام العربية (٠٥٠…) → إنجليزية بالجوال والواتساب قبل الفحص والحفظ
    private function normalizeNumbers(Request $request): void
    {
        foreach (['phone', 'whatsapp'] as $key) {
            if (is_string($request->input($key))) {
                $request->merge([$key => strtr($request->input($key), [
                    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
                    '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                ])]);
            }
        }
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeNumbers($request);
        $data = $request->validate([
            'name'                      => 'nullable|string|max:255',
            'type'                      => 'required|in:مالك,وكيل,وسيط,مكتب,مطور,مشروع',
            'phone'                     => 'required|string|max:20',
            'whatsapp'                  => ['required', 'string', 'max:20', 'regex:/(\D*\d){4,}/'],
            'email'                     => 'nullable|email',
            'city_id'                   => 'nullable|exists:cities,id',
            'address'                   => 'nullable|string',
            'exclusive_status'          => 'nullable|in:تم,لا,جاري,ملغي',
            'brokerage_status'          => 'nullable|in:تم,لا,جاري,ملغي',
            'auction_status'            => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'price_update_date'         => 'nullable|date',
            'agreement_end_date'        => 'nullable|date',
            'group'                     => 'nullable|in:A,B,C',
            'photo_status'              => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'design_status'             => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'video_status'              => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'publishing_status'         => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'sales_employee_owners_id'  => 'nullable|exists:employees,id',
            'sales_employee_leads_id'   => 'nullable|exists:employees,id',
            'notes'                     => 'nullable|string',
        ], self::WHATSAPP_MESSAGES);

        $data['created_by'] = $request->user()->id;
        $owner = Owner::create($data);

        // إشعار المدير
        NotificationLog::send(
            $request->user()->id,
            'تم إضافة مالك جديد',
            "تم إضافة المالك {$owner->name} برقم {$owner->owner_code}",
            'نجاح'
        );

        return $this->success($owner->load('city'), 'تم إضافة المالك بنجاح', 201);
    }

    // PUT /api/owners/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $owner = Owner::findOrFail($id);
        $this->normalizeNumbers($request);

        $data = $request->validate([
            'name'                     => 'sometimes|nullable|string|max:255',
            'type'                     => 'sometimes|in:مالك,وكيل,وسيط,مكتب,مطور,مشروع',
            'phone'                    => 'sometimes|string|max:20',
            'whatsapp'                 => ['sometimes', 'required', 'string', 'max:20', 'regex:/(\D*\d){4,}/'],
            'email'                    => 'nullable|email',
            'city_id'                  => 'nullable|exists:cities,id',
            'address'                  => 'nullable|string',
            'exclusive_status'         => 'nullable|in:تم,لا,جاري,ملغي',
            'brokerage_status'         => 'nullable|in:تم,لا,جاري,ملغي',
            'auction_status'           => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'price_update_date'        => 'nullable|date',
            'agreement_end_date'       => 'nullable|date',
            'group'                    => 'nullable|in:A,B,C',
            'photo_status'             => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'design_status'            => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'video_status'             => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'publishing_status'        => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'sales_employee_owners_id' => 'nullable|exists:employees,id',
            'sales_employee_leads_id'  => 'nullable|exists:employees,id',
            'notes'                    => 'nullable|string',
        ], self::WHATSAPP_MESSAGES);

        $owner->update($data);

        return $this->success($owner->fresh('city'), 'تم تحديث بيانات المالك');
    }

    // DELETE /api/owners/{id}
    public function destroy(int $id): JsonResponse
    {
        $owner = Owner::findOrFail($id);

        if ($owner->properties()->count() > 0) {
            return $this->error('لا يمكن حذف المالك لوجود عقارات مرتبطة به', 422);
        }

        $owner->delete();
        return $this->success(null, 'تم حذف المالك');
    }

    // GET /api/owners/{id}/properties — عقارات المالك (popup)
    public function properties(int $id): JsonResponse
    {
        $owner = Owner::findOrFail($id);
        $properties = $owner->properties()
            ->with('propertyType')
            ->select('id', 'property_code', 'name', 'property_type_id', 'status', 'listed_price')
            ->get();

        return $this->success([
            'owner'      => $owner->only(['id', 'name', 'owner_code']),
            'total'      => $properties->count(),
            'properties' => $properties,
        ]);
    }
}
