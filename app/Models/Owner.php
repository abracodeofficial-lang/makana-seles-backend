<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Owner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_code', 'name', 'type', 'phone', 'whatsapp', 'email', 'city_id', 'address',
        'exclusive_status', 'brokerage_status', 'auction_status',
        'price_update_date', 'agreement_end_date', 'group',
        'photo_status', 'design_status', 'video_status', 'publishing_status',
        'sales_employee_owners_id', 'sales_employee_leads_id', 'created_by',
        'notes',
    ];

    protected $casts = [
        'price_update_date'  => 'date',
        'agreement_end_date' => 'date',
    ];

    public function city(): BelongsTo                { return $this->belongsTo(City::class); }
    public function properties(): HasMany             { return $this->hasMany(Property::class); }
    public function salesEmployeeOwners(): BelongsTo  { return $this->belongsTo(Employee::class, 'sales_employee_owners_id'); }
    public function salesEmployeeLeads(): BelongsTo   { return $this->belongsTo(Employee::class, 'sales_employee_leads_id'); }
    public function createdBy(): BelongsTo            { return $this->belongsTo(Employee::class, 'created_by'); }

    public function getPropertiesCountAttribute(): int { return $this->properties()->count(); }

    public function isAgreementExpiringSoon(): bool
    {
        if (!$this->agreement_end_date) return false;
        return $this->agreement_end_date->diffInDays(now()) <= 7;
    }

    public function scopeByType($q, string $type)   { return $q->where('type', $type); }
    public function scopeByGroup($q, string $group) { return $q->where('group', $group); }

    protected static function booted(): void
    {
        static::creating(function (Owner $owner) {
            if (empty($owner->owner_code)) {
                $year  = now()->year;
                $count = static::whereYear('created_at', $year)->count() + 1;
                $owner->owner_code = 'OW-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}
