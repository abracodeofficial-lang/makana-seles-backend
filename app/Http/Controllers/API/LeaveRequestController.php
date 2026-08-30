<?php

namespace App\Http\Controllers\API;

use App\Models\{LeaveRequest, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = LeaveRequest::with(['employee:id,full_name,employee_number','leaveType:id,name']);

        if (!$user->hasPermission('leave_requests','approve')) {
            $query->where('employee_id', $user->id);
        }

        if ($request->status)        $query->where('final_status', $request->status);
        if ($request->employee_id)   $query->where('employee_id', $request->employee_id);
        if ($request->leave_type_id) $query->where('leave_type_id', $request->leave_type_id);

        $stats = [
            'total'    => $query->clone()->count(),
            'pending'  => $query->clone()->where('final_status','قيد المراجعة')->count(),
            'approved' => $query->clone()->where('final_status','معتمدة')->count(),
            'rejected' => $query->clone()->where('final_status','مرفوضة')->count(),
        ];

        return response()->json(['status'=>true,'stats'=>$stats,'data'=>$query->latest()->paginate(15)->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date'     => 'required|date|after_or_equal:today',
            'to_date'       => 'required|date|after_or_equal:from_date',
            'reason'        => 'required|string',
            'attachment'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $employee = $request->user();
        $balance  = $employee->getLeaveBalance($data['leave_type_id']);
        $days     = LeaveRequest::calcDays($data['from_date'], $data['to_date']);

        if ($balance && $balance->total_days !== 999 && $balance->remaining_days < $days) {
            return $this->error("الرصيد غير كافٍ، متبقي {$balance->remaining_days} يوم فقط", 422);
        }

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')
                ->store("leave-requests/{$employee->id}", 'public');
        }

        $data['employee_id'] = $employee->id;
        $req = LeaveRequest::create($data);

        NotificationLog::sendToApprovers('leave_requests', 'طلب إجازة جديد',
            "قدم {$employee->full_name} طلب إجازة من {$data['from_date']} إلى {$data['to_date']}",
            'معلومة', '/leaves');

        return $this->success($req->load('leaveType'), 'تم إرسال طلب الإجازة بنجاح', 201);
    }

    public function managerApprove(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string']);
        $req = LeaveRequest::where('manager_status','قيد الانتظار')->findOrFail($id);
        $req->approveByManager($request->user()->id, $request->notes);
        NotificationLog::send($req->employee_id, 'موافقة المدير على إجازتك', 'وافق المدير على طلب إجازتك وهو الآن قيد مراجعة HR', 'معلومة');
        NotificationLog::sendToApprovers('leave_requests', 'طلب إجازة بانتظار اعتماد HR',
            "وافق المدير على طلب إجازة {$req->employee->full_name} — بانتظار اعتماد الموارد البشرية",
            'معلومة', '/leaves');
        return $this->success(null, 'تمت الموافقة وإحالتها لـ HR');
    }

    public function managerReject(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'required|string']);
        $req = LeaveRequest::findOrFail($id);
        $req->rejectByManager($request->user()->id, $request->notes);
        NotificationLog::send($req->employee_id, 'رفض طلب الإجازة', "تم رفض طلب إجازتك: {$request->notes}", 'تحذير');
        return $this->success(null, 'تم رفض الطلب');
    }

    public function managerInquire(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'required|string']);
        $req = LeaveRequest::findOrFail($id);
        $req->returnByManager($request->user()->id, $request->notes);
        NotificationLog::send($req->employee_id, 'استفسار على طلب إجازتك',
            "طلب المدير توضيحاً بخصوص طلب إجازتك: {$request->notes}", 'تحذير');
        return $this->success(null, 'تم إرسال الاستفسار للموظف');
    }

    public function hrApprove(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string']);
        $req = LeaveRequest::where('manager_status','موافق')->findOrFail($id);
        $req->approveByHR($request->user()->id, $request->notes);
        NotificationLog::send($req->employee_id, 'اعتماد إجازتك',
            "تم اعتماد إجازتك من {$req->from_date->format('Y-m-d')} إلى {$req->to_date->format('Y-m-d')}", 'نجاح');
        return $this->success(null, 'تم اعتماد الإجازة وتحديث الحضور والرصيد تلقائياً');
    }

    public function hrReject(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'required|string']);
        $req = LeaveRequest::findOrFail($id);
        $req->rejectByHR($request->user()->id, $request->notes);
        NotificationLog::send($req->employee_id, 'رفض طلب الإجازة من HR', "رفض قسم HR طلب إجازتك: {$request->notes}", 'تحذير');
        return $this->success(null, 'تم رفض الطلب');
    }

    public function hrInquire(Request $request, int $id): JsonResponse
    {
        $request->validate(['notes' => 'required|string']);
        $req = LeaveRequest::findOrFail($id);
        $req->returnByHR($request->user()->id, $request->notes);
        NotificationLog::send($req->employee_id, 'استفسار على طلب إجازتك',
            "طلب قسم الموارد البشرية توضيحاً بخصوص طلب إجازتك: {$request->notes}", 'تحذير');
        return $this->success(null, 'تم إرسال الاستفسار للموظف');
    }

    // رد الموظف على استفسار المدير/HR على نفس الطلب (بدون تقديم طلب جديد)
    public function clarify(Request $request, int $id): JsonResponse
    {
        $request->validate(['clarification' => 'required|string']);

        $req = LeaveRequest::where('employee_id', $request->user()->id)->findOrFail($id);

        if ($req->manager_status !== 'إرجاع' && $req->hr_status !== 'إرجاع') {
            return $this->error('هذا الطلب لا ينتظر توضيحاً حالياً', 422);
        }

        $req->clarify($request->clarification);

        NotificationLog::sendToApprovers('leave_requests', 'رد الموظف على الاستفسار',
            "قدّم {$req->employee->full_name} توضيحاً على طلب الإجازة {$req->request_number}",
            'معلومة', '/leaves');

        return $this->success($req->fresh(), 'تم إرسال التوضيح، الطلب أصبح قيد المراجعة مجدداً');
    }
}
