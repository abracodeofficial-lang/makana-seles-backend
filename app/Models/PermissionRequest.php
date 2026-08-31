<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


// ============================================================
// PERMISSION REQUEST (إذن)
// ============================================================
class PermissionRequest extends Model
{
    protected $fillable = [
        'request_number', 'employee_id', 'type', 'duration', 'reason', 'clarification',
        'request_datetime',
        'manager_status', 'manager_notes', 'manager_id', 'manager_decision_at',
        'hr_status', 'hr_decision_at',
        'final_status',
    ];

    protected $casts = [
        'request_datetime'    => 'datetime',
        'manager_decision_at' => 'datetime',
        'hr_decision_at'      => 'datetime',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function manager(): BelongsTo  { return $this->belongsTo(Employee::class, 'manager_id'); }

    public function scopePending($q)  { return $q->where('final_status', 'قيد المراجعة'); }
    public function scopeApproved($q) { return $q->where('final_status', 'موافق'); }

    public function approveByManager(int $managerId, ?string $notes = null): void
    {
        $this->update([
            'manager_status'      => 'موافق',
            'manager_notes'       => $notes,
            'manager_id'          => $managerId,
            'manager_decision_at' => now(),
            'hr_status'           => 'معتمد',
            'hr_decision_at'      => now(),
            'final_status'        => 'موافق',
        ]);
    }

    public function rejectByManager(int $managerId, string $notes): void
    {
        $this->update([
            'manager_status'      => 'مرفوض',
            'manager_notes'       => $notes,
            'manager_id'          => $managerId,
            'manager_decision_at' => now(),
            'final_status'        => 'مرفوض',
        ]);
    }

    // إرجاع الطلب للموظف لتوضيح إضافي
    public function returnByManager(int $managerId, string $notes): void
    {
        $this->update([
            'manager_status'      => 'إرجاع',
            'manager_notes'       => $notes,
            'manager_id'          => $managerId,
            'manager_decision_at' => now(),
        ]);
    }

    // رد الموظف على استفسار المدير — يرجّع الطلب لقيد المراجعة
    public function clarify(string $text): void
    {
        $this->update([
            'clarification'  => $text,
            'manager_status' => 'قيد المراجعة',
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (PermissionRequest $req) {
            if (empty($req->request_number)) {
                $count = static::count() + 1;
                $req->request_number = 'PR-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}