<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Property extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'property_code', 'name', 'property_type_id', 'marketing_type_id',
        'usage_type_id', 'status', 'is_verified', 'source',
        'listed_price', 'net_price', 'price_per_meter', 'market_price_3months',
        'market_price_note', 'discount_amount',
        'total_area', 'land_area', 'building_area',
        'city_id', 'neighborhood_id', 'street', 'building_number', 'postal_code',
        'direction', 'map_url', 'latitude', 'longitude', 'nearby_places',
        'owner_id', 'advertiser_role', 'mortgage_status', 'mortgage_amount',
        'rental_status', 'annual_income', 'annual_return_rate', 'tenants_count',
        'contract_duration', 'contract_type_rental', 'rental_info', 'contract_end_date',
        'rooms_count', 'bathrooms_count', 'halls_count', 'kitchens_count',
        'floors_count', 'units_count', 'dimensions', 'street_width', 'building_age',
        'is_furnished', 'has_pool', 'has_garden', 'has_elevator',
        'parking', 'ac_type', 'has_internet', 'has_security', 'heating_type',
        'description', 'features', 'components', 'additional_info',
        'photos_url', 'video_url', 'virtual_tour_url', 'photos_count',
        'photo_status', 'design_status', 'video_status', 'marketing_status',
        'assigned_employee_id', 'commission_rate', 'created_by',
    ];

    protected $casts = [
        'is_verified'      => 'boolean',
        'is_furnished'     => 'boolean',
        'has_pool'         => 'boolean',
        'has_garden'       => 'boolean',
        'has_elevator'     => 'boolean',
        'has_internet'     => 'boolean',
        'has_security'     => 'boolean',
        'contract_end_date'=> 'date',
        'listed_price'     => 'decimal:2',
        'net_price'        => 'decimal:2',
        'total_area'       => 'decimal:2',
        'land_area'        => 'decimal:2',
        'building_area'    => 'decimal:2',
        'latitude'         => 'decimal:7',
        'longitude'        => 'decimal:7',
    ];

    public function owner(): BelongsTo            { return $this->belongsTo(Owner::class); }
    public function propertyType(): BelongsTo     { return $this->belongsTo(PropertyType::class); }
    public function marketingType(): BelongsTo    { return $this->belongsTo(MarketingType::class); }
    public function usageType(): BelongsTo        { return $this->belongsTo(UsageType::class); }
    public function city(): BelongsTo             { return $this->belongsTo(City::class); }
    public function neighborhood(): BelongsTo     { return $this->belongsTo(Neighborhood::class); }
    public function assignedEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'assigned_employee_id'); }
    public function createdBy(): BelongsTo        { return $this->belongsTo(Employee::class, 'created_by'); }
    public function documents(): HasMany          { return $this->hasMany(PropertyDocument::class); }
    public function visits(): HasMany             { return $this->hasMany(Visit::class); }

    public function getCalculatedPricePerMeterAttribute(): float
    {
        if (!$this->total_area || $this->total_area == 0) return 0;
        return round($this->listed_price / $this->total_area, 2);
    }

    public function getReturnRateAttribute(): float
    {
        if (!$this->listed_price || $this->listed_price == 0) return 0;
        return round(($this->annual_income / $this->listed_price) * 100, 2);
    }

    public function scopeAvailable($q)           { return $q->where('status', 'متاح'); }
    public function scopeVerified($q)             { return $q->where('is_verified', true); }
    public function scopeByCity($q, $cityId)      { return $q->where('city_id', $cityId); }
    public function scopeByType($q, $typeId)      { return $q->where('property_type_id', $typeId); }
    public function scopeByOwner($q, $ownerId)    { return $q->where('owner_id', $ownerId); }

    protected static function booted(): void
    {
        static::creating(function (Property $prop) {
            if (empty($prop->property_code)) {
                $year  = now()->year;
                $count = static::whereYear('created_at', $year)->count() + 1;
                $prop->property_code = 'RE-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
            if ($prop->total_area > 0) {
                $prop->price_per_meter = round($prop->listed_price / $prop->total_area, 2);
            }
        });

        static::updating(function (Property $prop) {
            if ($prop->total_area > 0) {
                $prop->price_per_meter = round($prop->listed_price / $prop->total_area, 2);
            }
        });
    }
}
