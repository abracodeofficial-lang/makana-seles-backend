<?php

namespace App\Http\Controllers\API;

use App\Models\{Lead, NotificationLog, Employee, Owner, Property, PropertyType, OperationPropertyTypeAssignment};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends BaseController
{
    private const PRICE_CATEGORIES = ['أقل من 4M', 'بين 4-10M', 'أعلى من 10M'];
    private const DIRECTIONS       = ['شمالية', 'جنوبية', 'شرقية', 'غربية', 'شمالية شرقية', 'شمالية غربية', 'جنوبية شرقية', 'جنوبية غربية', 'وسطى'];
    private const REQUEST_STATUSES = ['مفتوح', 'مغلق'];

    // بيانات العقار اللي بتنعرض مع المهتم: الكود - الحي - المنطقة - السعر - النوع
    private const PROPERTY_SUMMARY      = 'properties:id,property_code,property_type_id,city_id,neighborhood_id,listed_price';
    private const PROPERTY_SUMMARY_RELS = ['properties.propertyType:id,name', 'properties.city:id,name', 'properties.neighborhood:id,name'];

    public function applyFilters($query, Request $request)
    {
        if ($request->search)        $query->where(function($q) use ($request) {
            $q->where('name','like',"%{$request->search}%")
              ->orWhere('phone','like',"%{$request->search}%")
              ->orWhere('lead_code','like',"%{$request->search}%")
              ->orWhereHas('properties', fn($p) => $p->where('property_code', 'like', "%{$request->search}%"));
        });
        if ($request->classification)    $query->where('classification', $request->classification);
        if ($request->request_status)    $query->where('request_status', $request->request_status);
        if ($request->update_status)     $query->where('update_status', $request->update_status);
        if ($request->property_type_id)  $query->where('property_type_id', $request->property_type_id);
        if ($request->employee_id)       $query->where('operation_employee_id', $request->employee_id);
        if ($request->specialist_id)     $query->where('broker_employee_id', $request->specialist_id);
        if ($request->operation_status)  $query->where('operation_status', $request->operation_status);
        if ($request->specialist_stage)  $query->where('specialist_stage', $request->specialist_stage);
        if ($request->name)              $query->where('name', 'like', "%{$request->name}%");
        if ($request->phone)             $query->where('phone', 'like', "%{$request->phone}%");
        if ($request->budget_min)        $query->where('budget', '>=', $request->budget_min);
        if ($request->budget_max)        $query->where('budget', '<=', $request->budget_max);
        if ($request->date_from)         $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)           $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->seriousness_level) $query->where('seriousness_level', $request->seriousness_level);
        if ($request->follow_up_from)    $query->whereDate('follow_up_date', '>=', $request->follow_up_from);
        if ($request->follow_up_to)      $query->whereDate('follow_up_date', '<=', $request->follow_up_to);
        if ($request->source)            $query->where('source', $request->source);
        if ($request->applicant_type)    $query->where('applicant_type', $request->applicant_type);
        if ($request->city_id)           $query->where('city_id', $request->city_id);
        if ($request->neighborhood_id)   $query->where('neighborhood_id', $request->neighborhood_id);
        if ($request->direction)         $query->where('direction', $request->direction);
        if ($request->price_category)    $query->where('price_category', $request->price_category);

        return $query;
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->applyFilters(Lead::with([
            'propertyType:id,name',
            'city:id,name',
            'operationEmployee:id,full_name',
            'brokerEmployee:id,full_name',
            'createdBy:id,full_name',
            self::PROPERTY_SUMMARY,
            ...self::PROPERTY_SUMMARY_RELS,
        ]), $request);

        // إحصائيات وتوزيعات — بتتبع نفس الفلاتر المطبّقة على الجدول.
        // كل قسم بوكسات بيتجاهل فلتره الخاص (حتى لما تختار قيمة، باقي القيم ما بتصير صفر).
        $base        = fn() => $this->applyFilters(Lead::query(), $request);
        $baseWithout = fn(string $key) => $this->applyFilters(Lead::query(), new Request($request->except([$key, 'page'])));
        $countBy     = fn(string $filterKey, string $column) => $baseWithout($filterKey)
            ->selectRaw("$column as k, count(*) as c")->whereNotNull($column)->groupBy($column)->pluck('c', 'k');

        $stats = [
            'total'        => $base()->count(),
            'serious'      => $base()->where('classification','جاد')->count(),
            'today_visits' => \App\Models\Visit::whereDate('visit_date', today())
                                ->whereIn('lead_id', $base()->select('leads.id'))->count(),
            'inquiries'    => $base()->where('update_status','استفسار')->count(),
        ];

        $fixed = fn(array $values, $counts) => collect($values)
            ->map(fn($v) => ['label' => $v, 'count' => (int) ($counts[$v] ?? 0)])->values();

        $byBudget    = $fixed(self::PRICE_CATEGORIES, $countBy('price_category', 'price_category'));
        $byDirection = $fixed(self::DIRECTIONS, $countBy('direction', 'direction'));
        $byStatus    = $fixed(self::REQUEST_STATUSES, $countBy('request_status', 'request_status'));

        $typeCounts = $countBy('property_type_id', 'property_type_id');
        $byType = PropertyType::orderBy('id')->get(['id', 'name', 'is_active'])
            ->filter(fn($t) => $t->is_active || isset($typeCounts[$t->id]))
            ->map(fn($t) => ['id' => $t->id, 'label' => $t->name, 'count' => (int) ($typeCounts[$t->id] ?? 0)])->values();

        // الأوبريشن: المخصصين من الإعدادات + أي حدا عنده مهتمين كأوبريشن
        $opCounts = $countBy('employee_id', 'operation_employee_id');
        $opIds = OperationPropertyTypeAssignment::pluck('employee_id')
            ->merge(Lead::whereNotNull('operation_employee_id')->distinct()->pluck('operation_employee_id'))->unique();
        $byOperation = Employee::whereIn('id', $opIds)->orderBy('full_name')->get(['id', 'full_name'])
            ->map(fn($e) => ['id' => $e->id, 'label' => $e->full_name, 'count' => (int) ($opCounts[$e->id] ?? 0)])->values();

        // الأخصائيين: أي حدا انحوّلوله مهتمين
        $spCounts = $countBy('specialist_id', 'broker_employee_id');
        $spIds = Lead::whereNotNull('broker_employee_id')->distinct()->pluck('broker_employee_id');
        $bySpecialist = Employee::whereIn('id', $spIds)->orderBy('full_name')->get(['id', 'full_name'])
            ->map(fn($e) => ['id' => $e->id, 'label' => $e->full_name, 'count' => (int) ($spCounts[$e->id] ?? 0)])->values();

        return response()->json([
            'status'        => true,
            'stats'         => $stats,
            'by_budget'     => $byBudget,
            'by_direction'  => $byDirection,
            'by_status'     => $byStatus,
            'by_type'       => $byType,
            'by_operation'  => $byOperation,
            'by_specialist' => $bySpecialist,
            'data'          => $query->latest()->paginate(15)->toArray(),
        ]);
    }

    // GET /api/leads/export — كل السجلات المطابقة للفلاتر، بدون تقسيم صفحات
    public function export(Request $request): JsonResponse
    {
        $records = $this->applyFilters(Lead::with([
            'propertyType:id,name',
            'city:id,name',
            'operationEmployee:id,full_name',
            'brokerEmployee:id,full_name',
        ]), $request)->latest()->get();

        return $this->success($records);
    }

    public function show(int $id): JsonResponse
    {
        $lead = Lead::with([
            'propertyType','city','neighborhood',
            'paymentMethod','operationEmployee','brokerEmployee',
            'createdBy','visits.property','visits.assignedEmployee',
            self::PROPERTY_SUMMARY, ...self::PROPERTY_SUMMARY_RELS,
        ])->findOrFail($id);
        $lead->follow_up_status_computed = $lead->follow_up_status;
        return $this->success($lead);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'nullable|string|max:255',
            'phone'                 => 'required|string|max:20',
            'applicant_type'        => 'required|in:مهتم,مشتري,مستأجر,وسيط,وكيل,مطور',
            'source'                => 'required|string',
            'property_type_id'      => 'nullable|exists:property_types,id',
            'direction'             => 'nullable|in:شمالية,جنوبية,شرقية,غربية,شمالية شرقية,شمالية غربية,جنوبية شرقية,جنوبية غربية,وسطى',
            'city_id'               => 'nullable|exists:cities,id',
            'neighborhood_id'       => 'nullable|exists:neighborhoods,id',
            'offered_price'         => 'nullable|numeric',
            'budget'                => 'nullable|numeric',
            'price_category'        => 'nullable|in:أقل من 4M,بين 4-10M,أعلى من 10M',
            'classification'        => 'nullable|in:جاد,استفسار,بحث',
            'seriousness_level'     => 'nullable|in:1,2,3,4',
            'purchase_goal'         => 'nullable|in:استثمار,سكن,سكن واستثمار',
            'payment_method_id'     => 'nullable|exists:payment_methods,id',
            'operation_employee_id' => 'nullable|exists:employees,id',
            'broker_employee_id'    => 'nullable|exists:employees,id',
            'follow_up_date'        => 'nullable|date',
            'update_notes'          => 'nullable|string',
            'property_ids'          => 'nullable|array',
            'property_ids.*'        => 'integer|exists:properties,id',
        ]);
        $data['created_by'] = $request->user()->id;
        unset($data['property_ids']);
        $lead = Lead::create($data);
        $lead->properties()->sync($request->input('property_ids') ?? []);
        return $this->success($lead->load(['propertyType', 'city', self::PROPERTY_SUMMARY, ...self::PROPERTY_SUMMARY_RELS]), 'تم إضافة المهتم بنجاح', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $request->validate([
            'property_ids'   => 'nullable|array',
            'property_ids.*' => 'integer|exists:properties,id',
        ]);
        $lead->update($request->except(['lead_code', 'created_by', 'property_ids']));
        if ($request->has('property_ids')) $lead->properties()->sync($request->input('property_ids') ?? []);
        return $this->success($lead->fresh()->load([self::PROPERTY_SUMMARY, ...self::PROPERTY_SUMMARY_RELS]), 'تم تحديث بيانات المهتم');
    }

    // GET /api/leads/property-lookup?code=RE-2026-001 — بيانات عقار بالكود لربطه بالمهتم
    public function propertyLookup(Request $request): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));
        if ($code === '') return $this->error('اكتب كود العقار', 422);

        $columns = ['id', 'property_code', 'property_type_id', 'city_id', 'neighborhood_id', 'listed_price'];
        $with    = ['propertyType:id,name', 'city:id,name', 'neighborhood:id,name'];

        $property = Property::with($with)->where('property_code', $code)->first($columns);
        // لو كتب آخر الكود بس (مثلاً 015) وفي عقار واحد بينتهي فيه
        if (!$property) {
            $matches = Property::with($with)->where('property_code', 'like', '%' . $code)->limit(2)->get($columns);
            if ($matches->count() === 1) $property = $matches->first();
        }
        if (!$property) return $this->error('ما في عقار بهاد الكود', 404);

        return $this->success($property);
    }

    public function destroy(int $id): JsonResponse
    {
        Lead::findOrFail($id)->delete();
        return $this->success(null, 'تم حذف المهتم');
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'update_status'  => 'required|in:تم البيع,استفسار,اهتمام أولي,اهتمام عالي,طلبات بحث',
            'update_notes'   => 'nullable|string',
            'follow_up_date' => 'nullable|date',
        ]);
        $lead = Lead::findOrFail($id);
        $lead->update([
            'update_status'  => $request->update_status,
            'update_notes'   => $request->update_notes,
            'update_date'    => today(),
            'follow_up_date' => $request->follow_up_date,
            'request_status' => $request->update_status === 'تم البيع' ? 'مغلق' : $lead->request_status,
        ]);
        return $this->success($lead->fresh(), 'تم تحديث الحالة');
    }

    // تحديث حالة الأوبريشن قبل التحويل للأخصائي
    public function updateOperationStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'operation_status' => 'required|in:' . implode(',', Lead::OPERATION_STATUSES),
        ]);
        $lead = Lead::findOrFail($id);
        $lead->update($data);
        return $this->success($lead->fresh(), 'تم تحديث حالة الأوبريشن');
    }

    // تحويل المهتم من الأوبريشن للأخصائي — يدوي، الأوبريشن يختار الأخصائي بنفسه
    public function assignSpecialist(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'broker_employee_id' => 'required|exists:employees,id',
        ]);
        $lead = Lead::findOrFail($id);
        $lead->update([
            'broker_employee_id'        => $data['broker_employee_id'],
            'assigned_to_specialist_at' => now(),
            'specialist_stage'          => $lead->specialist_stage ?: 'تواصل',
        ]);

        NotificationLog::send(
            $data['broker_employee_id'],
            'مهتم جديد محوّل إليك',
            "تم تحويل المهتم {$lead->name} ({$lead->lead_code}) إليك من الأوبريشن.",
            'مهتم',
            "/leads?lead={$lead->id}"
        );

        return $this->success($lead->fresh()->load('brokerEmployee:id,full_name'), 'تم تحويل المهتم للأخصائي');
    }

    // تحديث مرحلة الأخصائي — مرن بالترتيب، الوضوح أهم من التسلسل الصارم
    public function updateSpecialistStage(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'specialist_stage' => 'required|in:' . implode(',', Lead::SPECIALIST_STAGES),
            'agreed_amount'    => 'required_if:specialist_stage,حجز|nullable|numeric|min:0',
        ]);
        $lead = Lead::findOrFail($id);
        $lead->update([
            'specialist_stage' => $data['specialist_stage'],
            'agreed_amount'    => $data['specialist_stage'] === 'حجز' ? $data['agreed_amount'] : $lead->agreed_amount,
        ]);

        if ($data['specialist_stage'] === 'حجز') {
            NotificationLog::sendToApprovers(
                'finance',
                'حجز جديد بحاجة لمتابعة',
                "المهتم {$lead->name} ({$lead->lead_code}) وصل لمرحلة حجز بمبلغ متفق عليه " .
                    number_format((float) $data['agreed_amount'], 2) . ' ريال.',
                'حجز',
                "/leads?lead={$lead->id}"
            );
        }

        return $this->success($lead->fresh(), 'تم تحديث مرحلة الأخصائي');
    }

    // لوحة الأوبريشن: عدد المهتمين والملاك النشطين لكل موظف أوبريشن
    public function operationDashboard(): JsonResponse
    {
        $employeeIds = collect()
            ->merge(Lead::whereNotNull('operation_employee_id')->pluck('operation_employee_id'))
            ->merge(Owner::whereNotNull('sales_employee_owners_id')->pluck('sales_employee_owners_id'))
            ->merge(\App\Models\OperationPropertyTypeAssignment::pluck('employee_id'))
            ->unique()
            ->values();

        $employees = Employee::whereIn('id', $employeeIds)
            ->with('operationPropertyTypeAssignments.propertyType:id,name')
            ->get(['id', 'full_name']);

        $data = $employees->map(function (Employee $employee) {
            return [
                'employee_id'         => $employee->id,
                'full_name'           => $employee->full_name,
                'property_types'      => $employee->operationPropertyTypeAssignments
                    ->pluck('propertyType.name')->filter()->values(),
                'active_leads_count'  => Lead::where('operation_employee_id', $employee->id)
                    ->where('request_status', 'مفتوح')->count(),
                'active_owners_count' => Owner::where('sales_employee_owners_id', $employee->id)->count(),
            ];
        })->values();

        return $this->success($data);
    }
}
