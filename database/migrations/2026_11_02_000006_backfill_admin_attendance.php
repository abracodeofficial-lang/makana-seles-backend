<?php

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;

// طلب الإدارة: حضور وانصراف أحمد محمد السعيد بوقت الشيفت من 1 أكتوبر 2026 لتاريخ التشغيل.
// بيتخطى أيام العطلة وأي يوم إله سجل، واليوم الحالي ما بياخد انصراف قبل نهاية الشيفت.
return new class extends Migration
{
    private const NOTE = 'إضافة من الإدارة بوقت الشيفت';

    public function up(): void
    {
        $matches = Employee::where('full_name', 'أحمد محمد السعيد')->get();
        if ($matches->count() !== 1 || !$matches->first()->shift) {
            logger()->warning('backfill_admin_attendance: الموظف غير موجود/مكرر أو بدون شيفت — ما انضافت سجلات');
            return;
        }
        $employee = $matches->first();
        $shift    = $employee->shift;
        $weekend  = AttendanceSetting::current()->weekendDays();
        $now      = now(config('app.business_timezone'));

        for ($day = $now->copy()->setDate(2026, 10, 1)->startOfDay(); $day->lte($now); $day->addDay()) {
            if (in_array($day->dayOfWeek, $weekend)) continue;

            $start = $day->copy()->setTimeFromTimeString($shift->start_time);
            $end   = $day->copy()->setTimeFromTimeString($shift->end_time);
            if ($now->lt($start)) continue;

            if (Attendance::where('employee_id', $employee->id)->whereDate('date', $day->toDateString())->exists()) continue;

            Attendance::create([
                'employee_id' => $employee->id,
                'date'        => $day->toDateString(),
                'check_in'    => $start->format('H:i:s'),
                'check_out'   => $now->gte($end) ? $end->format('H:i:s') : null,
                'status'      => 'حاضر',
                'source'      => 'تلقائي',
                'notes'       => self::NOTE,
            ]);
        }
    }

    public function down(): void
    {
        Attendance::where('notes', self::NOTE)->delete();
    }
};
