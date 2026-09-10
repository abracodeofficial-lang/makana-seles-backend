<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationPropertyTypeAssignment extends Model
{
    protected $fillable = ['employee_id', 'property_type_id'];

    public function employee(): BelongsTo     { return $this->belongsTo(Employee::class); }
    public function propertyType(): BelongsTo { return $this->belongsTo(PropertyType::class); }
}
