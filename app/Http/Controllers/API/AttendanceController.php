<?php

namespace App\Http\Controllers\API;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->date_from ?? $request->date ?? today()->toDateString();
        $dateTo   = $request->date_to   ?? $request->date ?? today()->toDateString();
        if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

        // فلاتر مشتركة (الموظف، القسم، الشيفت، الحالة) — بتنطبق على البيانات والعدادات وسجلات التحديث التلقائي
        $applyFilters = function ($q) use ($request) {
            if ($request->employee_id) $q->where('employee_id', $request->employee_id);
            if ($request->status)      $q->where('status', $request->status);
            if ($request->search) $q->whereHas('employee', fn($e) =>
                $e->where('full_name', 'like', "%{$request->search}%")
            );
            if ($request->department_id) $q->whereHas('employee', fn($e) =>
                $e->where('department_id', $request->department_id)
            );
            if ($request->shift_id) $q->whereHas('employee', fn($e) =>
                $e->where('shift_id', $request->shift_id)
            );
            return $q;
        };

        $baseQuery = fn() => $applyFilters(Attendance::whereBetween('date', [$dateFrom, $dateTo]));

        $stats = [
            'present'  => $baseQuery()->where('status', 'حاضر')->count(),
            'late'     => $baseQuery()->where('status', 'متأخر')->count(),
            'absent'   => $baseQuery()->where('status', 'غياب')->count(),
            'on_leave' => $baseQuery()->where('status', 'إجازة')->count(),
        ];

        $query = $baseQuery()->with([
            'employee:id,full_name,employee_number,department_id,shift_id',
            'employee.department:id,name',
            'employee.shift:id,name',
        ]);

        $autoUpdated = $baseQuery()->with('employee:id,full_name')
            ->where('source', 'تلقائي')
            ->get();

        return response()->json([
            'status'       => true,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'stats'        => $stats,
            'data'         => $query->orderByDesc('date')->get(),
            'auto_updated' => $autoUpdated,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date'        => 'required|date',
            'check_in'    => 'nullable|date_format:H:i',
            'check_out'   => 'nullable|date_format:H:i',
            'status'      => 'required|in:حاضر,متأخر,غياب,إجازة',
            'notes'       => 'nullable|string',
        ]);

        // حساب التأخير تلقائياً من شيفت الموظف
        $lateMinutes = 0;
        if (!empty($data['check_in']) && in_array($data['status'], ['حاضر', 'متأخر'])) {
            $employee = Employee::with('shift')->find($data['employee_id']);
            if ($employee->shift) {
                $shiftStart    = Carbon::createFromTimeString($employee->shift->start_time);
                $actualCheckIn = Carbon::createFromTimeString($data['check_in']);
                $diffMins      = $shiftStart->diffInMinutes($actualCheckIn, false);
                $tolerance     = $employee->shift->late_tolerance_minutes ?? 15;

                if ($diffMins > $tolerance) {
                    $lateMinutes    = $diffMins;
                    $data['status'] = 'متأخر';
                }
            }
        }
        $data['late_minutes'] = $lateMinutes;

        $record = Attendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            array_merge($data, ['source' => 'يدوي'])
        );

        return $this->success($record->load('employee:id,full_name,employee_number'), 'تم تسجيل الحضور', 201);
    }

    public function approve(int $id): JsonResponse
    {
        $record = Attendance::findOrFail($id);
        $record->update([
            'is_approved' => true,
            'approved_by' => auth()->id(),
        ]);

        return $this->success($record, 'تم اعتماد السجل');
    }

    public function export(Request $request): JsonResponse
    {
        $records = Attendance::with('employee:id,full_name,employee_number,department_id')
            ->whereBetween('date', [
                $request->from ?? now()->startOfMonth()->toDateString(),
                $request->to   ?? now()->toDateString(),
            ])->get();

        return $this->success($records);
    }
}
