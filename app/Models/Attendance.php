<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ============================================================
// ATTENDANCE
// ============================================================
class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out', 'worked_hours',
        'status', 'late_minutes', 'break_start', 'break_end',
        'overtime_hours', 'late_deduction', 'early_leave_deduction',
        'occupancy_allowance', 'notes', 'daily_update', 'source', 'is_approved', 'approved_by',
    ];

    protected $casts = [
        'date'       => 'date',
        'worked_hours'    => 'decimal:2',
        'overtime_hours'  => 'decimal:2',
        'late_deduction'  => 'decimal:2',
        'early_leave_deduction' => 'decimal:2',
        'occupancy_allowance'   => 'decimal:2',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

    // هل متأخر؟
    public function isLate(): bool { return $this->status === 'متأخر'; }
    public function isAbsent(): bool { return $this->status === 'غياب'; }
    public function isOnLeave(): bool { return $this->status === 'إجازة'; }

    // حساب ساعات العمل تلقائياً عند الحفظ
    protected static function booted(): void
    {
        static::saving(function (Attendance $att) {
            if ($att->check_in && $att->check_out) {
                $in  = \Carbon\Carbon::parse($att->check_in);
                $out = \Carbon\Carbon::parse($att->check_out);
                $att->worked_hours = round($out->diffInMinutes($in) / 60, 2);
            }
        });
    }
}

