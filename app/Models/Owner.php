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
                $owner->owner_code = static::codeFromWhatsapp($owner->whatsapp ?: $owner->phone)
                    ?? \App\Support\SequentialCode::next(static::class, 'owner_code', 'OW-' . now()->year . '-');
            }
        });
    }

    // كود المالك = آخر 4 أرقام من رقم الواتساب (2442). إذا مستخدم: 2442-2، 2442-3...
    // (مع المحذوفين لأن الكود unique بالـDB)
    public static function codeFromWhatsapp(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);
        if (strlen($digits) < 4) return null;

        $base = substr($digits, -4);
        $code = $base;
        for ($i = 2; static::withTrashed()->where('owner_code', $code)->exists(); $i++) {
            $code = $base . '-' . $i;
        }
        return $code;
    }
}
