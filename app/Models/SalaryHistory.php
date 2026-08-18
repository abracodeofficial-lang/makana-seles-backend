<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;




// ============================================================
// SALARY HISTORY
// ============================================================
class SalaryHistory extends Model
{
    protected $fillable = [
        'employee_id', 'old_salary', 'new_salary',
        'reason', 'approved_by', 'effective_date',
    ];

    protected $casts = ['effective_date' => 'date'];

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(Employee::class, 'approved_by'); }
}