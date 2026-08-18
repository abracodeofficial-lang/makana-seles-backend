<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
// ============================================================
// PROPERTY DOCUMENT
// ============================================================
class PropertyDocument extends Model
{
    protected $fillable = ['property_id', 'type', 'file_path', 'file_name', 'note'];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
}


