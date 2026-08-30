<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


// ============================================================
// LEAVE REQUEST
// ============================================================
class LeaveRequest extends Model
{
    protected $fillable = [
        'request_number', 'employee_id', 'leave_type_id',
        'from_date', 'to_date', 'days_count', 'reason', 'attachment_path',
        'manager_status', 'manager_notes', 'manager_id', 'manager_decision_at',
        'hr_status', 'hr_notes', 'hr_employee_id', 'hr_decision_at',
        'final_status',
    ];

    protected $casts = [
        'from_date'           => 'date',
        'to_date'             => 'date',
        'manager_decision_at' => 'datetime',
        'hr_decision_at'      => 'datetime',
    ];

    // ---- Relations ----
    public function employee(): BelongsTo   { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo  { return $this->belongsTo(LeaveType::class); }
    public function manager(): BelongsTo    { return $this->belongsTo(Employee::class, 'manager_id'); }
    public function hrEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'hr_employee_id'); }
    public function autoLeave(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AutoLeave::class);
    }

    // ---- Scopes ----
    public function scopePending($q)  { return $q->where('final_status', 'قيد المراجعة'); }
    public function scopeApproved($q) { return $q->where('final_status', 'معتمدة'); }
    public function scopeRejected($q) { return $q->where('final_status', 'مرفوضة'); }

    // ---- Helpers ----

    // حساب عدد الأيام تلقائياً
    public static function calcDays(string $from, string $to): int
    {
        return \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1;
    }

    // اعتماد المدير — يُحوّل لـ HR تلقائياً
    public function approveByManager(int $managerId, ?string $notes = null): void
    {
        $this->update([
            'manager_status'      => 'موافق',
            'manager_notes'       => $notes,
            'manager_id'          => $managerId,
            'manager_decision_at' => now(),
            'hr_status'           => 'قيد الانتظار', // ينتقل لـ HR
        ]);
    }

    // رفض المدير — ينتهي هنا
    public function rejectByManager(int $managerId, string $notes): void
    {
        $this->update([
            'manager_status'      => 'مرفوض',
            'manager_notes'       => $notes,
            'manager_id'          => $managerId,
            'manager_decision_at' => now(),
            'final_status'        => 'مرفوضة',
        ]);
    }

    // إرجاع الطلب للموظف لتوضيح إضافي — المدير
    public function returnByManager(int $managerId, string $notes): void
    {
        $this->update([
            'manager_status'      => 'إرجاع',
            'manager_notes'       => $notes,
            'manager_id'          => $managerId,
            'manager_decision_at' => now(),
        ]);
    }

    // إرجاع الطلب للموظف لتوضيح إضافي — HR
    public function returnByHR(int $hrId, string $notes): void
    {
        $this->update([
            'hr_status'      => 'إرجاع',
            'hr_notes'       => $notes,
            'hr_employee_id' => $hrId,
            'hr_decision_at' => now(),
        ]);
    }

    // اعتماد HR — ينشئ auto_leave ويحدّث الرصيد والحضور
    public function approveByHR(int $hrId, ?string $notes = null): void
    {
        $this->update([
            'hr_status'      => 'موافق',
            'hr_notes'       => $notes,
            'hr_employee_id' => $hrId,
            'hr_decision_at' => now(),
            'final_status'   => 'معتمدة',
        ]);

        // إنشاء auto_leave
        AutoLeave::create([
            'leave_request_id' => $this->id,
            'employee_id'      => $this->employee_id,
            'leave_type_id'    => $this->leave_type_id,
            'from_date'        => $this->from_date,
            'to_date'          => $this->to_date,
            'days_count'       => $this->days_count,
            'status'           => 'معتمدة',
        ]);

        // تحديث رصيد الإجازة
        LeaveBalance::where('employee_id', $this->employee_id)
            ->where('leave_type_id', $this->leave_type_id)
            ->where('year', $this->from_date->year)
            ->increment('used_days', $this->days_count);

        // تحديث سجل الحضور: كل أيام الإجازة → إجازة
        $current = $this->from_date->copy();
        while ($current->lte($this->to_date)) {
            Attendance::updateOrCreate(
                ['employee_id' => $this->employee_id, 'date' => $current->toDateString()],
                ['status' => 'إجازة', 'source' => 'تلقائي']
            );
            $current->addDay();
        }
    }

    // رفض HR
    public function rejectByHR(int $hrId, string $notes): void
    {
        $this->update([
            'hr_status'      => 'مرفوض',
            'hr_notes'       => $notes,
            'hr_employee_id' => $hrId,
            'hr_decision_at' => now(),
            'final_status'   => 'مرفوضة',
        ]);
    }

    // auto-generate request_number
    protected static function booted(): void
    {
        static::creating(function (LeaveRequest $req) {
            if (empty($req->request_number)) {
                $count = static::count() + 1;
                $req->request_number = 'LR-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
            // حساب عدد الأيام تلقائياً
            if ($req->from_date && $req->to_date) {
                $req->days_count = self::calcDays($req->from_date, $req->to_date);
            }
        });
    }
}