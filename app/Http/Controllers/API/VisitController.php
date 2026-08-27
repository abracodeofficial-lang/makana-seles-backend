<?php

namespace App\Http\Controllers\API;

use App\Models\{Visit, Lead, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Visit::with([
            'lead:id,name,phone,lead_code',
            'property:id,name,property_code',
            'assignedEmployee:id,full_name',
        ]);

        if ($request->date)        $query->whereDate('visit_date', $request->date);
        if ($request->status)      $query->where('status', $request->status);
        if ($request->employee_id) $query->where('assigned_employee_id', $request->employee_id);
        if ($request->lead_id)     $query->where('lead_id', $request->lead_id);

        $stats = [
            'total'     => Visit::count(),
            'today'     => Visit::whereDate('visit_date', today())->count(),
            'confirmed' => Visit::where('status','مؤكدة')->count(),
            'done'      => Visit::where('status','تمت')->count(),
        ];

        return response()->json(['status'=>true,'stats'=>$stats,'data'=>$query->latest('visit_date')->paginate(15)->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_id'              => 'required|exists:leads,id',
            'property_id'          => 'nullable|exists:properties,id',
            'location'             => 'required|string',
            'detailed_address'     => 'nullable|string',
            'visit_date'           => 'required|date|after_or_equal:today',
            'visit_time'           => 'required|date_format:H:i',
            'assigned_employee_id' => 'nullable|exists:employees,id',
            'notes'                => 'nullable|string',
        ]);

        $visit = Visit::create($data);

        Lead::where('id', $data['lead_id'])->update([
            'visit_date'        => $data['visit_date'],
            'visit_coordinated' => true,
            'visit_status'      => 'مجدولة',
        ]);

        if (!empty($data['assigned_employee_id'])) {
            NotificationLog::send(
                $data['assigned_employee_id'],
                'موعد زيارة جديد',
                "لديك موعد زيارة بتاريخ {$data['visit_date']} الساعة {$data['visit_time']}",
                'تحذير',
                "/visits/{$visit->id}"
            );
        }

        return $this->success($visit->load(['lead','property','assignedEmployee']), 'تم حجز الزيارة بنجاح', 201);
    }

    public function confirm(int $id): JsonResponse
    {
        $visit = Visit::findOrFail($id);
        $visit->update(['status' => 'مؤكدة']);
        Lead::where('id', $visit->lead_id)->update(['visit_status' => 'مؤكدة']);
        NotificationLog::send(
            $visit->assigned_employee_id ?? auth()->id(),
            'تم تأكيد الزيارة',
            "تم تأكيد موعد الزيارة للعقار {$visit->property?->name}",
            'نجاح'
        );
        return $this->success($visit->fresh(), 'تم تأكيد الزيارة');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $visit = Visit::findOrFail($id);
        $visit->update(['status' => 'ملغية', 'notes' => $request->reason]);
        Lead::where('id', $visit->lead_id)->update(['visit_status' => 'ملغية']);
        return $this->success(null, 'تم إلغاء الزيارة');
    }
}
