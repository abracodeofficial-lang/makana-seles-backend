<?php

namespace App\Http\Controllers\API;

use App\Models\{Owner, Property, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerController extends BaseController
{
    private function applyFilters($query, Request $request)
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

        // إحصائيات أعلى الصفحة
        $stats = [
            'total'               => Owner::count(),
            'individuals'         => Owner::where('type', 'مالك')->count(),
            'developers'          => Owner::where('type', 'مطور')->count(),
            'offices'             => Owner::where('type', 'مكتب')->count(),
            'projects'            => Owner::where('type', 'مشروع')->count(),
            'properties_added'    => Property::count(),
            'properties_accepted' => Property::where('status', 'متاح')->count(),
            'properties_verified' => Property::where('is_verified', true)->count(),
            'avg_price'           => round((float) Property::avg('listed_price'), 2),
        ];

        $byOwnerType = Owner::selectRaw('type, count(*) as count')
            ->groupBy('type')->get()
            ->map(fn($r) => ['label' => $r->type, 'count' => $r->count])->values();

        $byPropertyType = Property::selectRaw('property_type_id, count(*) as count')
            ->whereNotNull('property_type_id')->groupBy('property_type_id')
            ->with('propertyType:id,name')->get()
            ->map(fn($r) => ['label' => $r->propertyType?->name ?? '—', 'count' => $r->count])->values();

        $byDirection = Property::selectRaw('direction, count(*) as count')
            ->whereNotNull('direction')->groupBy('direction')->get()
            ->map(fn($r) => ['label' => $r->direction, 'count' => $r->count])->values();

        return response()->json([
            'status'            => true,
            'stats'             => $stats,
            'by_owner_type'     => $byOwnerType,
            'by_property_type'  => $byPropertyType,
            'by_direction'      => $byDirection,
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
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                      => 'required|string|max:255',
            'type'                      => 'required|in:مالك,وكيل,وسيط,مكتب,مطور,مشروع',
            'phone'                     => 'required|string|max:20',
            'whatsapp'                  => 'nullable|string|max:20',
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
        ]);

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

        $data = $request->validate([
            'name'                     => 'sometimes|string|max:255',
            'type'                     => 'sometimes|in:مالك,وكيل,وسيط,مكتب,مطور,مشروع',
            'phone'                    => 'sometimes|string|max:20',
            'whatsapp'                 => 'nullable|string|max:20',
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
        ]);

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
