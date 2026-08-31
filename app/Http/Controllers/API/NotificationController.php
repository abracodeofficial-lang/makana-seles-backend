<?php

namespace App\Http\Controllers\API;

use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $base = NotificationLog::where('employee_id', $request->user()->id);

        $stats = [
            'total'    => $base->clone()->count(),
            'unread'   => $base->clone()->where('is_read', false)->count(),
            'success'  => $base->clone()->where('type','نجاح')->count(),
            'warnings' => $base->clone()->where('type','تحذير')->count(),
        ];

        $query = $base->clone()->latest();
        if ($request->type)   $query->where('type', $request->type);
        if ($request->unread) $query->where('is_read', false);

        return response()->json(['status'=>true,'stats'=>$stats,'data'=>$query->paginate(20)->toArray()]);
    }

    public function markRead(int $id): JsonResponse
    {
        NotificationLog::where('employee_id', auth()->id())->findOrFail($id)->update(['is_read' => true]);
        return $this->success(null, 'تم تحديد الإشعار كمقروء');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        NotificationLog::where('employee_id', $request->user()->id)->where('is_read', false)->update(['is_read' => true]);
        return $this->success(null, 'تم تحديد الكل كمقروء');
    }

    public function destroy(int $id): JsonResponse
    {
        NotificationLog::where('employee_id', auth()->id())->findOrFail($id)->delete();
        return $this->success(null, 'تم حذف الإشعار');
    }

    public function clearAll(Request $request): JsonResponse
    {
        NotificationLog::where('employee_id', $request->user()->id)->delete();
        return $this->success(null, 'تم مسح جميع الإشعارات');
    }
}
