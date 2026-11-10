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
        'employee_number', 'full_name', 'national_id', 'phone', 'whatsapp', 'email',
        'password', 'address', 'marital_status', 'status',
        'department_id', 'job_title', 'contract_type_id', 'shift_id', 'hire_date',
        'basic_salary', 'housing_allowance', 'transport_allowance',
        'phone_allowance', 'other_allowances', 'commission_rate', 'min_sales_target',
    ];

    protected $hidden = ['password', 'remember_token'];

    // صيغة موحّدة للأرقام: بدون مسافات، و 00 بالأول بتصير +
    public static function cleanPhone(?string $phone): ?string
    {
        if ($phone === null) return null;
        $p = preg_replace('/[\s\-()]/', '', trim($phone));
        if (str_starts_with($p, '00')) $p = '+' . substr($p, 2);
        return $p === '' ? null : $p;
    }

    public function setPhoneAttribute($value): void    { $this->attributes['phone'] = self::cleanPhone($value); }
    public function setWhatsappAttribute($value): void { $this->attributes['whatsapp'] = self::cleanPhone($value); }

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
    public function shift(): BelongsTo        { return $this->belongsTo(Shift::class)->withTrashed(); }

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

    public function operationPropertyTypeAssignments(): HasMany
    {
        return $this->hasMany(OperationPropertyTypeAssignment::class);
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

    // الأدمن: عضو بمجموعة "مدير النظام"، أو عنده كل الصلاحيات على كل الصفحات
    public function isAdmin(): bool
    {
        if ($this->permissionGroups()->where('name', 'مدير النظام')->exists()) return true;

        foreach (array_keys(\App\Support\PermissionPages::all()) as $page) {
            foreach (['view', 'add', 'edit', 'delete', 'export', 'approve'] as $action) {
                if (!$this->hasPermission($page, $action)) return false;
            }
        }
        return true;
    }

    // الناشر (عنده صفحة النشر بدون تعديل العقارات) ما بيعدّل بيانات العقار
    public function canWriteProperties(): bool
    {
        if ($this->isAdmin() || $this->hasPermission('properties', 'edit')) return true;
        return !$this->hasPermission('publishing', 'view');
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
                $emp->employee_number = \App\Support\SequentialCode::next(static::class, 'employee_number', 'E-' . $year . '-');
            }
        });
    }
}
