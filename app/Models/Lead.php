<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lead_code', 'name', 'phone',
        'applicant_type', 'source',
        'property_type_id', 'city_id', 'neighborhood_id',
        'offered_price', 'budget', 'price_category', 'is_available',
        'classification', 'seriousness_level', 'purchase_goal', 'payment_method_id',
        'operation_employee_id', 'broker_employee_id', 'created_by',
        'operation_status', 'specialist_stage', 'assigned_to_specialist_at', 'agreed_amount',
        'request_status', 'update_status', 'update_date', 'update_notes',
        'follow_up_date', 'visit_date', 'visit_coordinated', 'visit_status',
    ];

    protected $casts = [
        'is_available'              => 'boolean',
        'visit_coordinated'         => 'boolean',
        'update_date'               => 'date',
        'follow_up_date'            => 'date',
        'visit_date'                => 'date',
        'assigned_to_specialist_at' => 'datetime',
        'budget'                    => 'decimal:2',
        'offered_price'             => 'decimal:2',
        'agreed_amount'             => 'decimal:2',
    ];

    // مسار مراحل الأخصائي — للعرض والترتيب بالواجهة فقط، التقدم مرن وليس إجبارياً
    public const SPECIALIST_STAGES = ['تواصل', 'معلومات واستفسارات', 'زيارة', 'إقناع', 'تفاوض', 'تفاهم', 'حجز'];
    public const OPERATION_STATUSES = ['يبغى تواصل هاتفي', 'عنده استفسارات أكثر', 'اهتمام مبدئي'];

    public function propertyType(): BelongsTo      { return $this->belongsTo(PropertyType::class); }
    public function city(): BelongsTo              { return $this->belongsTo(City::class); }
    public function neighborhood(): BelongsTo      { return $this->belongsTo(Neighborhood::class); }
    public function paymentMethod(): BelongsTo     { return $this->belongsTo(PaymentMethod::class); }
    public function operationEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'operation_employee_id'); }
    public function brokerEmployee(): BelongsTo    { return $this->belongsTo(Employee::class, 'broker_employee_id'); }
    public function createdBy(): BelongsTo         { return $this->belongsTo(Employee::class, 'created_by'); }
    public function visits(): HasMany              { return $this->hasMany(Visit::class); }

    public function getFollowUpStatusAttribute(): string
    {
        if (!$this->follow_up_date) return '-';
        $today    = now()->startOfDay();
        $followUp = $this->follow_up_date->startOfDay();
        if ($followUp->eq($today)) return 'اليوم';
        if ($followUp->gt($today)) return 'قادم';
        return 'متأخر';
    }

    public function getLastVisitAttribute(): ?Visit
    {
        return $this->visits()->latest('visit_date')->first();
    }

    public function scopeOpen($q)            { return $q->where('request_status', 'مفتوح'); }
    public function scopeSeriousOnly($q)     { return $q->where('classification', 'جاد'); }
    public function scopeFollowUpToday($q)   { return $q->whereDate('follow_up_date', today()); }
    public function scopeOverdue($q)
    {
        return $q->where('request_status', 'مفتوح')->whereDate('follow_up_date', '<', today());
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            if (empty($lead->lead_code)) {
                $year  = now()->year;
                $count = static::whereYear('created_at', $year)->count() + 1;
                $lead->lead_code = 'INT-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}
