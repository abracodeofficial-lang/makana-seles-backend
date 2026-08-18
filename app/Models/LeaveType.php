<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


// ============================================================
class LeaveType extends Model
{
    protected $fillable = ['name', 'total_days', 'requires_attachment', 'is_active'];
    protected $casts    = ['requires_attachment' => 'boolean', 'is_active' => 'boolean'];

    public function leaveRequests(): HasMany  { return $this->hasMany(LeaveRequest::class); }
    public function leaveBalances(): HasMany  { return $this->hasMany(LeaveBalance::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    // هل الرصيد غير محدود؟ (بدون أجر)
    public function isUnlimited(): bool { return $this->total_days === 999; }
}

