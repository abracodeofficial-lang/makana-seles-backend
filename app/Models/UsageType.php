<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ============================================================
class UsageType extends Model
{
    protected $fillable = ['name', 'details', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function properties(): HasMany { return $this->hasMany(Property::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
}

