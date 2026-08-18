<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes;

    protected $fillable = [
        'employee_number', 'full_name', 'national_id', 'phone', 'email',
        'password', 'address', 'marital_status', 'status',
        'department_id', 'job_title', 'contract_type_id', 'shift_id', 'hire_date',
        'basic_salary', 'housing_allowance', 'transport_allowance',
        'phone_allowance', 'other_allowances', 'commission_rate', 'min_sales_target',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'hire_date'    => 'date',
        'basic_salary' => 'decimal:2',
        'housing_allowance'   => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'phone_allowance'     => 'decimal:2',
        'other_allowances'    => 'decimal:2',
        'commission_rate'     => 'decimal:2',
        'min_sales_target'    => 'decimal:2',
    ];

    // ============================================================
    // Accessors
    // ============================================================

    // إجمالي الدخل الشهري
    public function getTotalSalaryAttribute(): float
    {
        return (float)($this->basic_salary        ?? 0)
             + (float)($this->housing_allowance   ?? 0)
             + (float)($this->transport_allowance ?? 0)
             + (float)($this->phone_allowance     ?? 0)
             + (float)($this->other_allowances    ?? 0);
    }

    // حالة المتابعة (للمهتمين المسندين للموظف)
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'نشط';
    }

    // ============================================================
    // Relations — Lookups
    // ============================================================
    public function department(): BelongsTo   { return $this->belongsTo(Department::class); }
    public function contractType(): BelongsTo { return $this->belongsTo(ContractType::class); }
    public function shift(): BelongsTo        { return $this->belongsTo(Shift::class); }

    // ============================================================
    // Relations — HR
    // ============================================================
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function permissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function salaryHistory(): HasMany
    {
        return $this->hasMany(SalaryHistory::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    // ============================================================
    // Relations — Sales (كموظف مسؤول)
    // ============================================================
    public function assignedProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'assigned_employee_id');
    }

    public function createdProperties(): HasMany
    {
        return $this->hasMany(Property::class, 'created_by');
    }

    public function ownersAsLeadsSales(): HasMany
    {
        return $this->hasMany(Owner::class, 'sales_employee_leads_id');
    }

    public function ownersAsOwnersSales(): HasMany
    {
        return $this->hasMany(Owner::class, 'sales_employee_owners_id');
    }

    public function operationLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'operation_employee_id');
    }

    public function brokerLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'broker_employee_id');
    }

    public function assignedVisits(): HasMany
    {
        return $this->hasMany(Visit::class, 'assigned_employee_id');
    }

    // ============================================================
    // Relations — Permissions
    // ============================================================
    public function permissionGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            PermissionGroup::class,
            'employee_permission_groups'
        );
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(EmployeePermissionOverride::class);
    }

    // هل للموظف صلاحية معينة على صفحة معينة؟ (الاستثناء الفردي يتجاوز صلاحيات المجموعة)
    public function hasPermission(string $page, string $action = 'view'): bool
    {
        $column = 'can_' . $action; // can_view, can_add, can_edit...

        $override = $this->permissionOverrides()->where('page_key', $page)->first();
        if ($override && $override->{$column} !== null) {
            return (bool) $override->{$column};
        }

        return $this->permissionGroups()
            ->join('permission_group_pages', 'permission_groups.id', '=', 'permission_group_pages.permission_group_id')
            ->where('permission_group_pages.page_key', $page)
            ->where("permission_group_pages.{$column}", true)
            ->exists();
    }

    // ============================================================
    // Scopes
    // ============================================================
    public function scopeActive($q)    { return $q->where('status', 'نشط'); }
    public function scopeByDept($q, $deptId) { return $q->where('department_id', $deptId); }

    // ============================================================
    // Helpers
    // ============================================================

    // رصيد إجازة معينة للسنة الحالية
    public function getLeaveBalance(int $leaveTypeId): ?LeaveBalance
    {
        return $this->leaveBalances()
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', now()->year)
            ->first();
    }

    // الحضور لتاريخ معين
    public function getAttendanceForDate(string $date): ?Attendance
    {
        return $this->attendances()->whereDate('date', $date)->first();
    }

    // auto-generate employee_number قبل الحفظ
    protected static function booted(): void
    {
        static::creating(function (Employee $emp) {
            if (empty($emp->employee_number)) {
                $year  = now()->year;
                $count = static::whereYear('created_at', $year)->count() + 1;
                $emp->employee_number = 'E-' . $year . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}
