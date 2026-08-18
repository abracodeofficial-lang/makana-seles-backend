<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;



// ============================================================
// EMPLOYEE DOCUMENT
// ============================================================
class EmployeeDocument extends Model
{
    protected $fillable = ['employee_id', 'type', 'name', 'file_path', 'file_name'];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}

