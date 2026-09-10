<?php

namespace App\Http\Controllers\API;

use App\Models\{Lead, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Lead::with([
            'propertyType:id,name',
            'city:id,name',
            'operationEmployee:id,full_name',
            'createdBy:id,full_name',
        ]);

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
        if ($request->name)              $query->where('name', 'like', "%{$request->name}%");
        if ($request->budget_min)        $query->where('budget', '>=', $request->budget_min);
        if ($request->budget_max)        $query->where('budget', '<=', $request->budget_max);
        if ($request->date_from)         $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to)           $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->seriousness_level) $query->where('seriousness_level', $request->seriousness_level);
        if ($request->follow_up_from)    $query->whereDate('follow_up_date', '>=', $request->follow_up_from);
        if ($request->follow_up_to)      $query->whereDate('follow_up_date', '<=', $request->follow_up_to);
        if ($request->source)            $query->where('source', $request->source);

        $stats = [
            'total'        => Lead::count(),
            'serious'      => Lead::where('classification','جاد')->count(),
            'today_visits' => \App\Models\Visit::whereDate('visit_date', today())->count(),
            'inquiries'    => Lead::where('update_status','استفسار')->count(),
        ];

        return response()->json(['status'=>true,'stats'=>$stats,'data'=>$query->latest()->paginate(15)->toArray()]);
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
}
