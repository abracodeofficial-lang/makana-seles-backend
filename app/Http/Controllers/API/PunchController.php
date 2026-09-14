<?php

namespace App\Http\Controllers\API;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PunchController extends BaseController
{
    // يرجّع تاريخ/وقت جهاز الموظف نفسه (مو وقت السيرفر) — بيوصلوا بالـ request
    private function clientDate(Request $request): string
    {
        return $request->input('date') ?: today()->toDateString();
    }

    private function clientTime(Request $request): string
    {
        return $request->input('time') ?: now()->format('H:i');
    }

    // ── حالة اليوم للموظف المسجّل ────────────────────────────
    public function today(Request $request): JsonResponse
    {
        $employee = auth()->user()->load('shift', 'department');
        $today    = $this->clientDate($request);

        $record = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        // إحصائيات الشهر الحالي
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->toDateString();

        $stats = [
            'open'      => Attendance::where('employee_id', $employee->id)
                            ->whereBetween('date', [$monthStart, $monthEnd])
                            ->whereNull('check_out')->whereNotNull('check_in')->count(),
            'completed' => Attendance::where('employee_id', $employee->id)
                            ->whereBetween('date', [$monthStart, $monthEnd])
                            ->whereNotNull('check_out')->count(),
            'total'     => Attendance::where('employee_id', $employee->id)
                            ->whereBetween('date', [$monthStart, $monthEnd])
                            ->count(),
        ];

        return $this->success([
            'employee' => $employee,
            'record'   => $record,
            'stats'    => $stats,
        ]);
    }

    // ── بصمة الحضور ─────────────────────────────────────────
    public function checkIn(Request $request): JsonResponse
    {
        $employee = auth()->user()->load('shift');
        $today    = $this->clientDate($request);
        $now      = $this->clientTime($request);

        $existing = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)->first();

        if ($existing?->check_in) {
            return $this->error('تم تسجيل الحضور مسبقاً اليوم');
        }

        // حساب التأخير من الشيفت
        $lateMinutes = 0;
        $status      = 'حاضر';

        if ($employee->shift) {
            $shiftStart    = Carbon::createFromTimeString($employee->shift->start_time);
            $actualCheckIn = Carbon::createFromTimeString($now);
            $diffMins      = $shiftStart->diffInMinutes($actualCheckIn, false);
            $tolerance     = $employee->shift->late_tolerance_minutes ?? 15;

            if ($diffMins > $tolerance) {
                $lateMinutes = $diffMins;
                $status      = 'متأخر';
            }
        }

        $record = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            [
                'check_in'     => $now,
                'status'       => $status,
                'late_minutes' => $lateMinutes,
                'source'       => 'يدوي',
            ]
        );

        $msg = $status === 'متأخر'
            ? "تم تسجيل الحضور — متأخر {$lateMinutes} دقيقة"
            : 'تم تسجيل الحضور بنجاح';

        return $this->success($record, $msg);
    }

    // ── بصمة الانصراف ───────────────────────────────────────
    public function checkOut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'daily_update' => 'required|string',
        ]);

        $employee = auth()->user();
        $today    = $this->clientDate($request);

        $record = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)->first();

        if (!$record || !$record->check_in) {
            return $this->error('لم يتم تسجيل الحضور بعد');
        }
        if ($record->check_out) {
            return $this->error('تم تسجيل الانصراف مسبقاً اليوم');
        }

        $record->update([
            'check_out'    => $this->clientTime($request),
            'daily_update' => $data['daily_update'],
        ]);

        return $this->success($record, 'تم تسجيل الانصراف بنجاح');
    }

    // ── بدء الاستراحة ───────────────────────────────────────
    public function breakStart(Request $request): JsonResponse
    {
        $employee = auth()->user();
        $today    = $this->clientDate($request);

        $record = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)->first();

        if (!$record || !$record->check_in) {
            return $this->error('لم يتم تسجيل الحضور بعد');
        }
        if ($record->break_start) {
            return $this->error('تم تسجيل بدء الاستراحة مسبقاً');
        }

        $record->update(['break_start' => $this->clientTime($request)]);

        return $this->success($record, 'تم تسجيل بدء الاستراحة');
    }

    // ── انتهاء الاستراحة ────────────────────────────────────
    public function breakEnd(Request $request): JsonResponse
    {
        $employee = auth()->user();
        $today    = $this->clientDate($request);

        $record = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)->first();

        if (!$record || !$record->break_start) {
            return $this->error('لم يتم تسجيل بدء الاستراحة بعد');
        }
        if ($record->break_end) {
            return $this->error('تم تسجيل انتهاء الاستراحة مسبقاً');
        }

        $record->update(['break_end' => $this->clientTime($request)]);

        return $this->success($record, 'تم تسجيل انتهاء الاستراحة');
    }

    // ── سجل الحضور الشخصي ───────────────────────────────────
    public function history(Request $request): JsonResponse
    {
        $employee = auth()->user();

        $records = Attendance::where('employee_id', $employee->id)
            ->when($request->month, fn($q) =>
                $q->whereRaw('YEAR(date) = ? AND MONTH(date) = ?', [
                    substr($request->month, 0, 4),
                    substr($request->month, 5, 2),
                ])
            )
            ->orderByDesc('date')
            ->get();

        return $this->success($records);
    }
}
