<?php

namespace App\Http\Controllers\API;

use App\Models\{Property, PropertyDocument, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyController extends BaseController
{
    private function applyFilters($query, Request $request)
    {
        if ($request->search)       $query->where(function($q) use ($request) {
            $q->where('name', 'like', "%{$request->search}%")
              ->orWhere('property_code', 'like', "%{$request->search}%")
              ->orWhereHas('owner', fn($o) => $o->where('name', 'like', "%{$request->search}%"));
        });
        if ($request->property_id)       $query->where('id', $request->property_id);
        if ($request->status)            $query->where('status', $request->status);
        if ($request->property_type_id)  $query->where('property_type_id', $request->property_type_id);
        if ($request->city_id)           $query->where('city_id', $request->city_id);
        if ($request->neighborhood_id)   $query->where('neighborhood_id', $request->neighborhood_id);
        if ($request->owner_id)          $query->where('owner_id', $request->owner_id);
        if ($request->is_verified !== null) $query->where('is_verified', $request->boolean('is_verified'));
        if ($request->marketing_type_id) $query->where('marketing_type_id', $request->marketing_type_id);
        if ($request->owner_name)        $query->whereHas('owner', fn($o) => $o->where('name', 'like', "%{$request->owner_name}%"));
        if ($request->date_from)         $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)           $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->direction)         $query->where('direction', $request->direction);
        if ($request->price_min)         $query->where('listed_price', '>=', $request->price_min);
        if ($request->price_max)         $query->where('listed_price', '<=', $request->price_max);
        if ($request->code)              $query->where('property_code', 'like', "%{$request->code}%");

        return $query;
    }

    // GET /api/properties
    public function index(Request $request): JsonResponse
    {
        $query = $this->applyFilters(Property::with([
            'owner:id,name,owner_code,phone,whatsapp',
            'propertyType:id,name',
            'city:id,name',
            'neighborhood:id,name',
        ]), $request);

        // إحصائيات
        $stats = [
            'total'           => Property::count(),
            'available'       => Property::where('status', 'متاح')->count(),
            'reserved'        => Property::where('status', 'محجوز')->count(),
            'sold'            => Property::where('status', 'مباع')->count(),
            'under_review'    => Property::where('status', 'قيد المراجعة')->count(),
            'marketing'       => Property::where('marketing_status', 'جاري')->count(),
        ];

        $byType = Property::selectRaw('property_type_id, count(*) as count')
            ->whereNotNull('property_type_id')
            ->groupBy('property_type_id')
            ->with('propertyType:id,name')
            ->get()
            ->map(fn($r) => ['label' => $r->propertyType?->name ?? '—', 'count' => $r->count])
            ->values();

        $byDirection = Property::selectRaw('direction, count(*) as count')
            ->whereNotNull('direction')
            ->groupBy('direction')
            ->get()
            ->map(fn($r) => ['label' => $r->direction, 'count' => $r->count])
            ->values();

        return response()->json([
            'status'       => true,
            'stats'        => $stats,
            'by_type'      => $byType,
            'by_direction' => $byDirection,
            'data'         => $query->latest()->paginate(12)->toArray(),
        ]);
    }

    // GET /api/properties/export — كل السجلات المطابقة للفلاتر، بدون تقسيم صفحات
    public function export(Request $request): JsonResponse
    {
        $records = $this->applyFilters(Property::with([
            'owner:id,name,owner_code',
            'propertyType:id,name',
            'city:id,name',
            'neighborhood:id,name',
        ]), $request)->latest()->get();

        return $this->success($records);
    }

    // GET /api/properties/{id}
    public function show(int $id): JsonResponse
    {
        $property = Property::with([
            'owner', 'propertyType', 'marketingType', 'usageType',
            'city', 'neighborhood', 'documents',
            'assignedEmployee:id,full_name,phone',
            'createdBy:id,full_name',
        ])->findOrFail($id);

        return $this->success($property);
    }

    // POST /api/properties
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // المعلومات الأساسية — الاسم والمالك فقط إلزاميان
            'name'               => 'required|string|max:255',
            'property_type_id'   => 'required|exists:property_types,id',
            'marketing_type_id'  => 'nullable|exists:marketing_types,id',
            'usage_type_id'      => 'nullable|exists:usage_types,id',
            'status'             => 'nullable|in:متاح,محجوز,مباع,قيد المراجعة',
            'source'             => 'nullable|string',
            // التسعير
            'listed_price'       => 'nullable|numeric|min:0',
            'net_price'          => 'nullable|numeric|min:0',
            'market_price_3months'   => 'nullable|numeric',
            'market_price_note'      => 'nullable|string',
            'discount_amount'        => 'nullable|numeric|min:0',
            // المساحة
            'total_area'         => 'nullable|numeric|min:0',
            'land_area'          => 'nullable|numeric',
            'building_area'      => 'nullable|numeric',
            // الموقع
            'city_id'            => 'nullable|exists:cities,id',
            'neighborhood_id'    => 'nullable|exists:neighborhoods,id',
            'street'             => 'nullable|string',
            'building_number'    => 'nullable|string',
            'direction'          => 'nullable|string',
            'map_url'            => 'nullable|url',
            'latitude'           => 'nullable|numeric',
            'longitude'          => 'nullable|numeric',
            'nearby_places'      => 'nullable|string',
            // المالك
            'owner_id'           => 'required|exists:owners,id',
            'advertiser_role'    => 'nullable|in:مالك,وكيل,مطور',
            'mortgage_status'    => 'nullable|in:مرهون,غير مرهون',
            'mortgage_amount'    => 'nullable|numeric',
            'rental_status'      => 'nullable|in:مؤجر,غير مؤجر,جزئي',
            'annual_income'      => 'nullable|numeric',
            'tenants_count'      => 'nullable|integer',
            'contract_end_date'  => 'nullable|date',
            'rental_info'        => 'nullable|string',
            // التفاصيل
            'rooms_count'        => 'nullable|integer',
            'bathrooms_count'    => 'nullable|integer',
            'halls_count'        => 'nullable|integer',
            'kitchens_count'     => 'nullable|integer',
            'floors_count'       => 'nullable|integer',
            'units_count'        => 'nullable|integer',
            'dimensions'         => 'nullable|string',
            'street_width'       => 'nullable|numeric',
            'building_age'       => 'nullable|integer',
            'is_furnished'       => 'nullable|boolean',
            'has_pool'           => 'nullable|boolean',
            'has_garden'         => 'nullable|boolean',
            'has_elevator'       => 'nullable|boolean',
            'parking'            => 'nullable|in:متوفر,غير متوفر',
            'ac_type'            => 'nullable|string',
            // المميزات والوسائط
            'description'        => 'nullable|string',
            'features'           => 'nullable|string',
            'components'         => 'nullable|string',
            'photos_url'         => 'nullable|url',
            'video_url'          => 'nullable|url',
            'virtual_tour_url'   => 'nullable|url',
            // التجهيز الفني
            'photo_status'       => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'design_status'      => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'video_status'       => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'marketing_status'   => 'nullable|in:جديد,تم,جاري,معلق,ملغي',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'commission_rate'    => 'nullable|numeric|min:0|max:100',
        ]);

        $data['created_by'] = $request->user()->id;
        $property = Property::create($data);

        NotificationLog::send(
            $request->user()->id,
            'تم إضافة عقار جديد',
            "تم إضافة {$property->name} برقم {$property->property_code}",
            'نجاح',
            "/properties/{$property->id}"
        );

        return $this->success($property->load(['owner', 'propertyType', 'city']), 'تم إضافة العقار بنجاح', 201);
    }

    // PUT /api/properties/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $property = Property::findOrFail($id);
        $property->update($request->except(['property_code', 'created_by']));
        return $this->success($property->fresh(['owner', 'propertyType', 'city']), 'تم تحديث العقار');
    }

    // DELETE /api/properties/{id}
    public function destroy(int $id): JsonResponse
    {
        Property::findOrFail($id)->delete();
        return $this->success(null, 'تم حذف العقار');
    }

    // POST /api/properties/{id}/documents
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $property = Property::findOrFail($id);

        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'type' => 'required|in:صك,رخصة بناء,مخططات,أخرى',
            'note' => 'nullable|string',
        ]);

        $file     = $request->file('file');
        $path     = $file->store("properties/{$id}/documents", 'public');
        $document = PropertyDocument::create([
            'property_id' => $id,
            'type'        => $request->type,
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),
            'note'        => $request->note,
        ]);

        return $this->success($document, 'تم رفع المستند بنجاح', 201);
    }

    // DELETE /api/properties/{id}/documents/{docId}
    public function deleteDocument(int $id, int $docId): JsonResponse
    {
        $doc = PropertyDocument::where('property_id', $id)->findOrFail($docId);
        Storage::disk('public')->delete($doc->file_path);
        $doc->delete();
        return $this->success(null, 'تم حذف المستند');
    }
}
