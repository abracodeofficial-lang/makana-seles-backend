<?php

namespace App\Http\Controllers\API;

use App\Models\AuditLog;
use App\Support\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// سجل العمليات — للأدمن فقط
class AuditLogController extends BaseController
{
    // GET /api/audit-logs?employee_id=&action=&subject_type=&date_from=&date_to=&search=&page=
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) return $this->error('هذه الصفحة للأدمن فقط', 403);

        $tz = config('app.business_timezone');   // التاريخ المختار بتوقيت الشغل، والتخزين UTC

        $query = AuditLog::query();
        if ($request->employee_id)  $query->where('employee_id', $request->employee_id);
        if ($request->action)       $query->where('action', $request->action);
        if ($request->subject_type) $query->where('subject_type', $request->subject_type);
        if ($request->date_from)    $query->where('created_at', '>=', \Carbon\Carbon::parse($request->date_from, $tz)->startOfDay()->utc());
        if ($request->date_to)      $query->where('created_at', '<=', \Carbon\Carbon::parse($request->date_to, $tz)->endOfDay()->utc());
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('subject_label', 'like', "%{$request->search}%")
                  ->orWhere('employee_name', 'like', "%{$request->search}%");
            });
        }

        $page = $query->latest('id')->paginate(30);

        return response()->json([
            'status'       => true,
            'data'         => $page->getCollection()->map(fn($l) => [
                'id'            => $l->id,
                'employee_name' => $l->employee_name,
                'action'        => $l->action,
                'type_label'    => AuditTrail::TYPES[$l->subject_type] ?? $l->subject_type,
                'subject_label' => $l->subject_label,
                'changes'       => $l->changes ?? [],
                'ip'            => $l->ip,
                'created_at'    => $l->created_at,
            ]),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
            'employees'    => AuditLog::whereNotNull('employee_id')->selectRaw('employee_id as id, MAX(employee_name) as name')
                                ->groupBy('employee_id')->orderBy('name')->get(),
            'types'        => collect(AuditTrail::TYPES)->map(fn($label, $key) => ['value' => $key, 'label' => $label])->values(),
        ]);
    }
}
