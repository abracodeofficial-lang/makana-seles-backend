<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


// ============================================================
// LEAVE BALANCE
// ============================================================
class LeaveBalance extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'year',
        'total_days', 'used_days', 'last_leave_date',
    ];

    protected $casts = [
        'last_leave_date' => 'date',
    ];

    // الرصيد المتبقي يُحسب
    public function getRemainingDaysAttribute(): int
    {
        if ($this->total_days === 999) return 999; // بدون أجر
        return max(0, $this->total_days - $this->used_days);
    }

    // نسبة الاستخدام %
    public function getUsagePercentageAttribute(): float
    {
        if ($this->total_days === 0 || $this->total_days === 999) return 0;
        return round(($this->used_days / $this->total_days) * 100, 1);
    }

    // تحذير: أقل من 30%؟
    public function isLow(): bool
    {
        return $this->total_days !== 999 && $this->usage_percentage >= 70;
    }

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
}