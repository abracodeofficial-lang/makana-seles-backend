<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;



// ============================================================
// PERMISSION GROUP PAGE
// ============================================================
class PermissionGroupPage extends Model
{
    protected $fillable = [
        'permission_group_id', 'page_key', 'page_name',
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

    public function group(): BelongsTo { return $this->belongsTo(PermissionGroup::class, 'permission_group_id'); }
}
