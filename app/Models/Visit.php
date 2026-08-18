<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};


// ============================================================
// VISIT
// ============================================================
class Visit extends Model
{
    protected $fillable = [
        'visit_code', 'lead_id', 'property_id', 'location',
        'detailed_address', 'visit_date', 'visit_time',
        'assigned_employee_id', 'status', 'notes',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'visit_time' => 'datetime:H:i',
    ];

    // ---- Relations ----
    public function lead(): BelongsTo     { return $this->belongsTo(Lead::class); }
    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    // ---- Scopes ----
    public function scopeToday($q)    { return $q->whereDate('visit_date', today()); }
    public function scopeUpcoming($q) { return $q->whereDate('visit_date', '>=', today()); }
    public function scopeConfirmed($q){ return $q->where('status', 'مؤكدة'); }

    // ---- Auto-generate code ----
    protected static function booted(): void
    {
        static::creating(function (Visit $visit) {
            if (empty($visit->visit_code)) {
                $year  = now()->year;
                $count = static::whereYear('created_at', $year)->count() + 1;
                $visit->visit_code = 'V-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}
