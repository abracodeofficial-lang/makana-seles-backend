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
        $date  = $request->date ?? today()->toDateString();
        $query = Attendance::with(['employee:id,full_name,employee_number,department_id'])
            ->whereDate('date', $date);

        if ($request->search) $query->whereHas('employee', fn($q) =>
            $q->where('full_name', 'like', "%{$request->search}%")
        );

        $stats = [
            'present'  => Attendance::whereDate('date', $date)->where('status', 'حاضر')->count(),
            'late'     => Attendance::whereDate('date', $date)->where('status', 'متأخر')->count(),
            'absent'   => Attendance::whereDate('date', $date)->where('status', 'غياب')->count(),
            'on_leave' => Attendance::whereDate('date', $date)->where('status', 'إجازة')->count(),
        ];

        $autoUpdated = Attendance::with('employee:id,full_name')
            ->whereDate('date', $date)
            ->where('source', 'تلقائي')
            ->get();

        return response()->json([
            'status'       => true,
            'date'         => $date,
            'stats'        => $stats,
            'data'         => $query->get(),
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
