<?php

namespace App\Http\Controllers\API;

use App\Models\AuditLog;
use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// سجل العمليات — للأدمن فقط
class AuditLogController extends BaseController
{
    // نوع السجل ← [الصفحة، الحقل اللي بيفتح التفاصيل (?open=)]
    private const LINKS = [
        'Property' => ['/properties', 'id'], 'PropertyDocument' => ['/properties', 'property_id'], 'PropertyImage' => ['/properties', 'property_id'],
        'PublishingSetting' => ['/publishing', null],
        'Owner' => ['/owners', 'id'], 'Lead' => ['/leads', 'id'], 'Visit' => ['/leads', 'lead_id'],
        'Employee' => ['/employees', 'id'], 'EmployeeDocument' => ['/employees', 'employee_id'], 'SalaryHistory' => ['/employees', 'employee_id'],
        'Attendance' => ['/attendance', null], 'LeaveRequest' => ['/leaves', null], 'LeaveBalance' => ['/leaves', null], 'AutoLeave' => ['/leaves', null],
        'PermissionRequest' => ['/permissions', null], 'ContactList' => ['/broadcast', null], 'WaCampaign' => ['/broadcast', null],
        'PermissionGroup' => ['/roles', null], 'PermissionGroupPage' => ['/roles', null], 'EmployeePermissionOverride' => ['/roles', null],
    ];

    // السجل المحذوف رابطه بصفحة المحذوفات
    private const TRASH_TYPES = [
        'Property' => 'properties', 'Owner' => 'owners', 'Lead' => 'leads', 'PropertyDocument' => 'property_documents', 'PropertyImage' => 'property_images',
        'EmployeeDocument' => 'employee_documents', 'ContactList' => 'contact_lists', 'WaCampaign' => 'wa_campaigns', 'City' => 'cities',
        'Neighborhood' => 'neighborhoods', 'PropertyType' => 'property_types', 'OperationPropertyTypeAssignment' => 'operation_assignments',
        'Shift' => 'shifts', 'LeaveType' => 'leave_types', 'PermissionDuration' => 'permission_durations', 'PermissionGroup' => 'permission_groups',
    ];

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

        // كل السجلات المذكورة بالصفحة دفعة وحدة (مع المحذوف)
        $subjects = $page->getCollection()->groupBy('subject_type')->map(function ($logs, $type) {
            $class = 'App\\Models\\' . $type;
            return class_exists($class)
                ? $class::withoutGlobalScopes()->whereIn('id', $logs->pluck('subject_id')->filter())->get()->keyBy('id')
                : collect();
        });

        return response()->json([
            'status'       => true,
            'data'         => $page->getCollection()->map(fn($l) => $this->linkFor($l, $subjects[$l->subject_type]->get($l->subject_id)) + [
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

    private function linkFor(AuditLog $log, ?Model $subject): array
    {
        $type = $log->subject_type;

        if ($subject && method_exists($subject, 'trashed') && $subject->trashed() && isset(self::TRASH_TYPES[$type])) {
            return ['url' => '/trash?type=' . self::TRASH_TYPES[$type], 'url_kind' => 'trash'];
        }

        [$path, $field] = self::LINKS[$type] ?? ['/settings', null];
        $openId = $subject && $field ? $subject->getAttribute($field) : null;

        return $openId
            ? ['url' => "$path?open=$openId", 'url_kind' => 'record']
            : ['url' => $path, 'url_kind' => 'page'];
    }
}
