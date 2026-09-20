<?php

namespace App\Http\Controllers\API;

use App\Models\{Lead, NotificationLog, Employee, Owner};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends BaseController
{
    private function applyFilters($query, Request $request)
    {
        if ($request->search)        $query->where(function($q) use ($request) {
            $q->where('name','like',"%{$request->search}%")
              ->orWhere('phone','like',"%{$request->search}%")
              ->orWhere('lead_code','like',"%{$request->search}%");
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
        ]), $request);

        // إحصائيات وتوزيعات — بتتبع نفس الفلاتر المطبّقة على الجدول
        $base = fn() => $this->applyFilters(Lead::query(), $request);

        $stats = [
            'total'        => $base()->count(),
            'serious'      => $base()->where('classification','جاد')->count(),
            'today_visits' => \App\Models\Visit::whereDate('visit_date', today())
                                ->whereIn('lead_id', $base()->select('leads.id'))->count(),
            'inquiries'    => $base()->where('update_status','استفسار')->count(),
        ];

        $groupCount = fn($column) => $base()->selectRaw("$column as label, count(*) as count")
            ->whereNotNull($column)->groupBy($column)->get()->values();

        $byBudget    = $groupCount('price_category');
        $byDirection = $groupCount('direction');
        $byStatus    = $groupCount('request_status');

        $byType = $base()->selectRaw('property_type_id, count(*) as count')
            ->whereNotNull('property_type_id')->groupBy('property_type_id')
            ->with('propertyType:id,name')->get()
            ->map(fn($r) => ['label' => $r->propertyType?->name ?? '—', 'count' => $r->count])->values();

        $byOperation = $base()->selectRaw('operation_employee_id, count(*) as count')
            ->whereNotNull('operation_employee_id')->groupBy('operation_employee_id')
            ->with('operationEmployee:id,full_name')->get()
            ->map(fn($r) => ['label' => $r->operationEmployee?->full_name ?? '—', 'count' => $r->count])->values();

        $bySpecialist = $base()->selectRaw('broker_employee_id, count(*) as count')
            ->whereNotNull('broker_employee_id')->groupBy('broker_employee_id')
            ->with('brokerEmployee:id,full_name')->get()
            ->map(fn($r) => ['label' => $r->brokerEmployee?->full_name ?? '—', 'count' => $r->count])->values();

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
        ])->findOrFail($id);
        $lead->follow_up_status_computed = $lead->follow_up_status;
        return $this->success($lead);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'phone'                 => 'required|string|max:20',
            'applicant_type'        => 'required|in:مهتم,مشتري,مستأجر,وسيط,وكيل,مطور',
            'source'                => 'required|string',
            'property_type_id'      => 'nullable|exists:property_types,id',
            'direction'             => 'nullable|in:شمالية,جنوبية,شرقية,غربية,شمالية شرقية,شمالية غربية,جنوبية شرقية,جنوبية غربية',
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
        ]);
        $data['created_by'] = $request->user()->id;
        $lead = Lead::create($data);
        return $this->success($lead->load(['propertyType','city']), 'تم إضافة المهتم بنجاح', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);
        $lead->update($request->except(['lead_code','created_by']));
        return $this->success($lead->fresh(), 'تم تحديث بيانات المهتم');
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
