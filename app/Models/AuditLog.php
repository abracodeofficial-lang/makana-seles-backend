<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['employee_id', 'employee_name', 'action', 'subject_type', 'subject_id', 'subject_label', 'changes', 'ip'];
    protected $casts    = ['changes' => 'array'];
}
