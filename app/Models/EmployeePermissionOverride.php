<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePermissionOverride extends Model
{
    protected $fillable = [
        'employee_id', 'page_key',
        'can_view', 'can_add', 'can_edit', 'can_delete', 'can_export', 'can_approve',
    ];

    protected $casts = [
        'can_view'    => 'boolean',
        'can_add'     => 'boolean',
        'can_edit'    => 'boolean',
        'can_delete'  => 'boolean',
        'can_export'  => 'boolean',
        'can_approve' => 'boolean',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
