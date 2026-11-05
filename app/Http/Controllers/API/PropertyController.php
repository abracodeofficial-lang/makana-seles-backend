<?php

namespace App\Http\Controllers\API;

use App\Models\{Property, PropertyDocument, PropertyType, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyController extends BaseController
{
    private const DIRECTIONS = ['شمالية', 'جنوبية', 'شرقية', 'غربية', 'شمالية شرقية', 'شمالية غربية', 'جنوبية شرقية', 'جنوبية غربية', 'وسطى'];

    // أعمدة بالـDB ما بتقبل NULL (إلها قيمة افتراضية) — حقل فاضي بالفورم بيوصل null وبيعمل 500
    private const NOT_NULL_DEFAULTS = [
        'listed_price' => 0, 'net_price' => 0, 'price_per_meter' => 0, 'market_price_3months' => 0,
        'discount_amount' => 0, 'total_area' => 0, 'land_area' => 0, 'building_area' => 0,
        'mortgage_amount' => 0, 'annual_income' => 0, 'annual_return_rate' => 0, 'tenants_count' => 0,
        'rooms_count' => 0, 'bathrooms_count' => 0, 'halls_count' => 0, 'kitchens_count' => 0,
        'floors_count' => 0, 'units_count' => 1, 'street_width' => 0, 'building_age' => 0,
        'photos_count' => 0, 'commission_rate' => 0,
        'is_verified' => false, 'is_furnished' => false, 'has_pool' => false, 'has_garden' => false,
        'has_elevator' => false, 'has_internet' => false, 'has_security' => false, 'has_brokerage_contract' => false,
        'status' => 'متاح', 'mortgage_status' => 'غير مرهون', 'rental_status' => 'غير مؤجر', 'parking' => 'غير متوفر',
        'photo_status' => 'جديد', 'design_status' => 'جديد', 'video_status' => 'جديد', 'marketing_status' => 'جديد',
    ];

    private function fillDefaults(array $data): array
    {
        foreach (self::NOT_NULL_DEFAULTS as $key => $default) {
            if (array_key_exists($key, $data) && $data[$key] === null) $data[$key] = $default;
        }
        return $data;
    }

    // النص الحر هو المصدر، والرقم بينحسب منه. طلب بالرقم بس (تبويب قديم مفتوح) بيلغي النص القديم
    private function resolveArea(array $data): array
    {
        if (array_key_exists('total_area_text', $data)) {
            $data['total_area'] = Property::parseArea($data['total_area_text']);
        } elseif (array_key_exists('total_area', $data)) {
            $data['total_area_text'] = null;
        }
        return $data;
    }

    private function applyFilters($query, Request $request)
    {
        if ($request->search)       $query->where(function($q) use ($request) {
            $q->where('name', 'like', "%{$request->search}%")
              ->orWhere('property_code', 'like', "%{$request->search}%")
              ->orWhere('trial_property_code', 'like', "%{$request->search}%")
              ->orWhere('license_number', 'like', "%{$request->search}%")
              ->orWhere('plan_number', 'like', "%{$request->search}%")
              ->orWhere('plot_number', 'like', "%{$request->search}%")
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
        if ($request->facade)            $query->where('facade', 'like', "%{$request->facade}%");
        if ($request->filled('has_brokerage_contract')) $query->where('has_brokerage_contract', (bool) $request->has_brokerage_contract);
        if ($request->price_min)         $query->where('listed_price', '>=', $request->price_min);
        if ($request->price_max)         $query->where('listed_price', '<=', $request->price_max);
        if ($request->code)              $query->where(fn($q) => $q->where('property_code', 'like', "%{$request->code}%")->orWhere('trial_property_code', 'like', "%{$request->code}%"));
        // فئة السعر: أقل من 4 مليون / بين 4 و10 / أعلى من 10
        match ($request->price_range) {
            'lt4'   => $query->where('listed_price', '<', 4_000_000),
            '4to10' => $query->whereBetween('listed_price', [4_000_000, 10_000_000]),
            'gt10'  => $query->where('listed_price', '>', 10_000_000),
            default => null,
        };

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

        // إحصائيات — بتتبع نفس الفلاتر المطبّقة على الجدول
        $base = fn() => $this->applyFilters(Property::query(), $request);

        $stats = [
            'total'           => $base()->count(),
            'available'       => $base()->where('status', 'متاح')->count(),
            'reserved'        => $base()->where('status', 'محجوز')->count(),
            'sold'            => $base()->where('status', 'مباع')->count(),
            'under_review'    => $base()->where('status', 'قيد المراجعة')->count(),
            'marketing'       => $base()->where('marketing_status', 'جاري')->count(),
        ];

        // كل أنواع العقارات المضافة (حتى اللي عددها صفر) — بتنعرض كبوكسات وبتنفع كخيارات للفلتر
        // كل بوكسات القسم بتتجاهل فلتر القسم نفسه (حتى لما تختار نوع، باقي الأنواع ما بتصير صفر)
        $baseWithout = fn(string $key) => $this->applyFilters(Property::query(), new Request($request->except([$key, 'page'])));

        $typeCounts = $baseWithout('property_type_id')->selectRaw('property_type_id, count(*) as count')
            ->whereNotNull('property_type_id')->groupBy('property_type_id')
            ->pluck('count', 'property_type_id');
        $byType = PropertyType::orderBy('id')->get(['id', 'name', 'is_active'])
            ->filter(fn($t) => $t->is_active || isset($typeCounts[$t->id]))
            ->map(fn($t) => ['id' => $t->id, 'label' => $t->name, 'count' => (int) ($typeCounts[$t->id] ?? 0)])
            ->values();

        $dirCounts = $baseWithout('direction')->selectRaw('direction, count(*) as count')
            ->whereNotNull('direction')->groupBy('direction')
            ->pluck('count', 'direction');
        $byDirection = collect(self::DIRECTIONS)
            ->map(fn($d) => ['label' => $d, 'count' => (int) ($dirCounts[$d] ?? 0)])
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
            'city', 'neighborhood', 'documents', 'images',
            'assignedEmployee:id,full_name,phone',
            'createdBy:id,full_name',
        ])->findOrFail($id);

        return $this->success($property);
    }

    // POST /api/properties
    public function store(Request $request): JsonResponse
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('property.write'), 403, 'ليس لديك صلاحية لتعديل العقارات');
        $data = $request->validate([
            // المعلومات الأساسية — الاسم والمالك فقط إلزاميان
            'name'               => 'required|string|max:255',
            'trial_property_code' => 'nullable|string|max:50',
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
            'total_area_text'    => 'nullable|string|max:50',
            'land_area'         => 'nullable|numeric',
            'building_area'      => 'nullable|numeric',
            // الموقع
            'city_id'            => 'nullable|exists:cities,id',
            'neighborhood_id'    => 'nullable|exists:neighborhoods,id',
            'street'             => 'nullable|string',
            'building_number'    => 'nullable|string',
            'direction'          => 'nullable|string',
            'facade'             => 'nullable|string|max:100',
            'has_brokerage_contract' => 'nullable|boolean',
            'license_number'     => 'nullable|string|max:50',
            'ad_license_expiry'  => 'nullable|date',
            'fal_license_number' => 'nullable|string|max:50',
            'listing_purpose'    => 'nullable|in:بيع,إيجار',
            'plan_number'        => 'nullable|string|max:50',
            'plot_number'        => 'nullable|string|max:50',
            'map_url'            => 'nullable|url',
            'latitude'           => 'nullable|numeric',
            'longitude'          => 'nullable|numeric',
            'nearby_places'      => 'nullable|string',
            // المالك
            'owner_id'           => 'nullable|exists:owners,id',
            'advertiser_role'    => 'nullable|in:مالك,وكيل,وسيط,مطور',
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

        $data = $this->fillDefaults($this->resolveArea($data));
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
        abort_unless(\Illuminate\Support\Facades\Gate::allows('property.write'), 403, 'ليس لديك صلاحية لتعديل العقارات');
        $property = Property::findOrFail($id);
        $request->validate([
            'total_area_text'    => 'nullable|string|max:50',
            'trial_property_code' => 'nullable|string|max:50',
            'ad_license_expiry'  => 'nullable|date',
            'fal_license_number' => 'nullable|string|max:50',
            'listing_purpose'    => 'nullable|in:بيع,إيجار',
        ]);
        $property->update($this->fillDefaults($this->resolveArea($request->except(['property_code', 'created_by']))));
        return $this->success($property->fresh(['owner', 'propertyType', 'city']), 'تم تحديث العقار');
    }

    // DELETE /api/properties/{id}
    public function destroy(int $id): JsonResponse
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('property.write'), 403, 'ليس لديك صلاحية لتعديل العقارات');
        $property = Property::findOrFail($id);
        if ($property->publications()->whereIn('status', ['published', 'needs_update', 'queued', 'publishing'])->exists()) {
            return $this->error('العقار منشور — أوقف نشره أول من صفحة النشر', 422);
        }
        $property->delete();
        return $this->success(null, 'تم حذف العقار');
    }

    // POST /api/properties/{id}/ready — مدخل البيانات: جاهز للنشر
    public function markReady(Request $request, int $id): JsonResponse
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('property.write'), 403, 'ليس لديك صلاحية لتعديل العقارات');
        $property = Property::findOrFail($id);
        if (!in_array($property->workflow_status, ['draft', 'returned'], true)) {
            return $this->error('العقار مُرسل للنشر من قبل', 422);
        }

        Property::whereKey($id)->update([
            'workflow_status' => 'ready_for_publish', 'return_note' => null,
            'ready_at' => now(), 'ready_by' => $request->user()->id,
        ]);

        app(\App\Services\Publishing\PublicationService::class)->notifyPublishers(
            'عقار جاهز للنشر', "{$property->name} ({$property->property_code})", 'معلومة', "/publishing/{$id}"
        );

        return $this->success(null, 'تم تحويل العقار لجاهز للنشر');
    }

    // POST /api/properties/{id}/images
    public function uploadImages(Request $request, int $id): JsonResponse
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('property.write'), 403, 'ليس لديك صلاحية لتعديل العقارات');
        $property = Property::findOrFail($id);
        $request->validate([
            'images'   => 'required|array|min:1|max:20',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
        ], ['images.*.image' => 'الملف لازم يكون صورة', 'images.*.max' => 'حجم الصورة أكبر من 10MB']);

        $next = (int) \App\Models\PropertyImage::where('property_id', $id)->max('sort_order');
        $saved = [];
        foreach ($request->file('images') as $file) {
            $saved[] = \App\Models\PropertyImage::create([
                'property_id' => $property->id,
                'file_path'   => $file->store("properties/{$id}/images", 'public'),
                'file_name'   => $file->getClientOriginalName(),
                'sort_order'  => ++$next,
            ]);
        }

        return $this->success($saved, 'تم رفع الصور', 201);
    }

    // DELETE /api/properties/{id}/images/{imageId}
    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('property.write'), 403, 'ليس لديك صلاحية لتعديل العقارات');
        \App\Models\PropertyImage::where('property_id', $id)->findOrFail($imageId)->delete();
        return $this->success(null, 'تم حذف الصورة');
    }

    // POST /api/properties/{id}/documents
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('property.write'), 403, 'ليس لديك صلاحية لتعديل العقارات');
        $property = Property::findOrFail($id);

        // ملف المشروع: PDF وصور بس — فحص الامتداد ومحتوى الملف الفعلي مع بعض
        $fileRule = $request->type === PropertyDocument::PROJECT_FILE
            ? 'required|file|extensions:pdf,jpg,jpeg,png,webp|mimes:pdf,jpg,jpeg,png,webp|max:20480'
            : 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';

        $request->validate([
            'file' => $fileRule,
            'type' => 'required|in:صك,رخصة بناء,مخططات,أخرى,' . PropertyDocument::PROJECT_FILE,
            'note' => 'nullable|string',
        ], [
            'file.extensions' => 'مسموح PDF وصور (JPG, PNG, WEBP) بس',
            'file.mimes'      => 'مسموح PDF وصور (JPG, PNG, WEBP) بس',
            'file.max'        => 'حجم الملف أكبر من الحد المسموح',
            'file.uploaded'   => 'تعذّر رفع الملف، ممكن حجمه أكبر من المسموح على السيرفر',
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

    // GET /api/properties/{id}/documents/{docId}/file — فتح الملف لمسجلي الدخول
    public function documentFile(int $id, int $docId)
    {
        $doc = PropertyDocument::where('property_id', $id)->findOrFail($docId);
        abort_unless(Storage::disk('public')->exists($doc->file_path), 404, 'الملف مش موجود');

        return response()->file(Storage::disk('public')->path($doc->file_path), ['X-Content-Type-Options' => 'nosniff']);
    }

    // DELETE /api/properties/{id}/documents/{docId}
    public function deleteDocument(int $id, int $docId): JsonResponse
    {
        abort_unless(\Illuminate\Support\Facades\Gate::allows('property.write'), 403, 'ليس لديك صلاحية لتعديل العقارات');
        $doc = PropertyDocument::where('property_id', $id)->findOrFail($docId);
        $doc->delete();   // حذف ناعم — الملف بيضل على القرص حتى ينفع الاسترجاع
        return $this->success(null, 'تم حذف المستند');
    }
}
