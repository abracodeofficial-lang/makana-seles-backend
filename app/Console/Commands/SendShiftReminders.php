<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\NotificationLog;
use App\Services\WebPushService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

// تذكيرات الدوام — بتشتغل كل دقيقة من الـ scheduler:
//   • قبل بداية الدوام بـ 10 دقائق: "دوامك رح يبدأ" (لمين لسا ما بصم حضور، مو بإجازة، ومو يوم عطلة)
//   • عند نهاية الدوام: "خلص دوامك" (لمين بصم حضور ولسا ما بصم انصراف)
class SendShiftReminders extends Command
{
    protected $signature   = 'attendance:shift-reminders';
    protected $description = 'إرسال تذكير قبل بداية الدوام بـ10 دقائق وعند نهايته';

    private const BEFORE_START_MINUTES = 10;
    // لو الـ cron تأخر أو فات دقيقة، منضل نبعت ضمن هالنافذة (والتكرار ممنوع بالكاش)
    private const END_WINDOW_MINUTES   = 15;

    public function handle(): int
    {
        // أوقات الشيفتات والبصمات مخزنة بالتوقيت المحلي للشركة، مو UTC
        $tz      = config('app.business_timezone', 'Asia/Riyadh');
        $now     = Carbon::now($tz);
        $weekend = AttendanceSetting::current()->weekendDays();

        $employees = Employee::active()->whereNotNull('shift_id')->with('shift')->get();

        foreach ($employees as $emp) {
            $shift = $emp->shift;
            if (!$shift?->start_time || !$shift?->end_time) continue;

            $this->maybeRemindStart($emp, $shift, $now, $tz, $weekend);
            $this->maybeRemindEnd($emp, $shift, $now, $tz);
        }

        Cache::put('scheduler:last_run', $now->toDateTimeString(), now()->addDay());
        return self::SUCCESS;
    }

    private function maybeRemindStart(Employee $emp, $shift, Carbon $now, string $tz, array $weekend): void
    {
        $start  = Carbon::parse($now->toDateString() . ' ' . $shift->start_time, $tz);
        $remind = $start->copy()->subMinutes(self::BEFORE_START_MINUTES);
        if ($now->lt($remind) || $now->gte($start)) return;
        if (in_array($start->dayOfWeek, $weekend, true)) return;

        $date = $start->toDateString();

        $checkedIn = Attendance::where('employee_id', $emp->id)
            ->whereDate('date', $date)->whereNotNull('check_in')->exists();
        if ($checkedIn) return;

        $onLeave = LeaveRequest::where('employee_id', $emp->id)
            ->where('final_status', 'معتمدة')
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->exists();
        if ($onLeave) return;

        if (!Cache::add("shift-reminder:start:{$emp->id}:{$date}", 1, now()->addDay())) return;

        $mins  = max(1, (int) ceil($now->diffInSeconds($start) / 60));
        $title = "⏰ دوامك رح يبدأ خلال {$mins} دقائق";
        $body  = 'يبدأ دوامك الساعة ' . substr($shift->start_time, 0, 5) . ' — لا تنسَ تبصم حضور.';

        NotificationLog::send($emp->id, $title, $body, 'تحذير', '/punch');
        WebPushService::sendToEmployee($emp->id, $title, $body, '/punch', "shift-start-{$date}");
    }

    private function maybeRemindEnd(Employee $emp, $shift, Carbon $now, string $tz): void
    {
        // دوام ليلي (النهاية قبل البداية): النهاية تابعة لدوام بلّش مبارح
        $overnight = substr($shift->end_time, 0, 5) <= substr($shift->start_time, 0, 5);
        $end       = Carbon::parse($now->toDateString() . ' ' . $shift->end_time, $tz);
        if ($now->lt($end) || $now->gte($end->copy()->addMinutes(self::END_WINDOW_MINUTES))) return;

        $workDate = $overnight ? $end->copy()->subDay()->toDateString() : $end->toDateString();

        $open = Attendance::where('employee_id', $emp->id)
            ->whereDate('date', $workDate)
            ->whereNotNull('check_in')->whereNull('check_out')
            ->exists();
        if (!$open) return;

        if (!Cache::add("shift-reminder:end:{$emp->id}:{$workDate}", 1, now()->addDays(2))) return;

        $title = '✅ خلص دوامك';
        $body  = 'انتهى دوامك الساعة ' . substr($shift->end_time, 0, 5) . ' — لا تنسَ تبصم انصراف وتكتب تحديثك اليومي.';

        NotificationLog::send($emp->id, $title, $body, 'معلومة', '/punch');
        WebPushService::sendToEmployee($emp->id, $title, $body, '/punch', "shift-end-{$workDate}");
    }
}
