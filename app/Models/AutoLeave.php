<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// ============================================================
// AUTO LEAVE
// ============================================================
class AutoLeave extends Model
{
    protected $fillable = [
        'leave_request_id', 'employee_id', 'leave_type_id',
        'from_date', 'to_date', 'days_count', 'status', 'created_source',
    ];

    protected $casts = ['from_date' => 'date', 'to_date' => 'date'];

    public function leaveRequest(): BelongsTo { return $this->belongsTo(LeaveRequest::class); }
    public function employee(): BelongsTo     { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo    { return $this->belongsTo(LeaveType::class); }
}