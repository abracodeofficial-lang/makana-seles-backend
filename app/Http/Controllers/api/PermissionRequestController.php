<?php

namespace App\Http\Controllers\API;

use App\Models\{PermissionRequest, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionRequestController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = PermissionRequest::with(['employee:id,full_name,employee_number,department_id']);

        if (!$user->hasPermission('permissions','approve')) {
            $query->where('employee_id', $user->id);
        }

        if ($request->status) $query->where('final_status', $request->status);

        $stats = [
            'pending'  => PermissionRequest::where('final_status','قيد المراجعة')->count(),
            'approved' => PermissionRequest::where('final_status','موافق')->count(),
            'rejected' => PermissionRequest::where('final_status','مرفوض')->count(),
        ];

        return response()->json(['status'=>true,'stats'=>$stats,'data'=>$query->latest()->paginate(15)->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'             => 'required|in:طبي,شخصي,حكومي',
            'duration'         => 'required|string|max:100|exists:permission_durations,name',
            'reason'           => 'required|string',
            'request_datetime' => 'required|date',
        ]);

        $data['employee_id'] = $request->user()->id;
        $perm = PermissionRequest::create($data);

        return $this->success($perm, 'تم إرسال طلب الإذن بنجاح', 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string']);
        $perm = PermissionRequest::where('final_status','قيد المراجعة')->findOrFail($id);
        $perm->approveByManager($request->user()->id, $request->notes);
        NotificationLog::send($perm->employee_id, 'موافقة على طلب الإذن', 'تمت الموافقة على طلب الإذن الخاص بك', 'نجاح');
        return $this->success(null, 'تمت الموافقة على الإذن');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'required|string']);
        $perm = PermissionRequest::findOrFail($id);
        $perm->rejectByManager($request->user()->id, $request->notes);
        NotificationLog::send($perm->employee_id, 'رفض طلب الإذن', "تم رفض طلب الإذن: {$request->notes}", 'تحذير');
        return $this->success(null, 'تم رفض الطلب');
    }
}
