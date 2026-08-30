<?php

namespace App\Http\Controllers\API;

use App\Models\{Employee, SalaryHistory, EmployeeDocument, LeaveBalance, NotificationLog};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Storage};

class EmployeeController extends BaseController
{
    // GET /api/employees
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['department:id,name', 'shift:id,name'])
            ->withCount(['assignedProperties as properties_count']);

        if ($request->search) $query->where(function($q) use ($request) {
            $q->where('full_name', 'like', "%{$request->search}%")
              ->orWhere('employee_number', 'like', "%{$request->search}%")
              ->orWhere('phone', 'like', "%{$request->search}%")
              ->orWhere('job_title', 'like', "%{$request->search}%");
        });
        if ($request->department_id) $query->where('department_id', $request->department_id);
        if ($request->status)        $query->where('status', $request->status);

        $stats = [
            'total'    => Employee::count(),
            'active'   => Employee::where('status', 'نشط')->count(),
            'on_leave' => Employee::where('status', 'إجازة')->count(),
            // إجمالي الرواتب
            'total_salaries' => Employee::where('status', 'نشط')
                ->selectRaw('SUM(basic_salary + housing_allowance + transport_allowance + phone_allowance + other_allowances) as total')
                ->value('total') ?? 0,
        ];

        return response()->json([
            'status' => true,
            'stats'  => $stats,
            'data'   => $query->latest()->paginate(12)->toArray(),
        ]);
    }

    // GET /api/employees/{id}
    public function show(int $id): JsonResponse
    {
        $employee = Employee::with([
            'department', 'contractType', 'shift',
            'documents', 'salaryHistory.approvedBy:id,full_name',
            'leaveBalances.leaveType',
            'permissionGroups.pages',
        ])->findOrFail($id);

        return $this->success([
            'employee'     => $employee,
            'total_salary' => $employee->total_salary,
        ]);
    }

    // POST /api/employees  — 3 خطوات لكنها request واحد
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // خطوة 1 — مطلوبة
            'full_name'       => 'required|string|max:255',
            'national_id'     => 'required|string|unique:employees',
            'phone'           => 'required|string|unique:employees',
            'email'           => 'required|email|unique:employees',
            'password'        => 'required|min:8',
            'address'         => 'required|string',
            'marital_status'  => 'required|in:أعزب,متزوج,مطلق,أرمل',
            // خطوة 2 — اختيارية
            'department_id'    => 'nullable|exists:departments,id',
            'job_title'        => 'nullable|string',
            'contract_type_id' => 'nullable|exists:contract_types,id',
            'shift_id'         => 'nullable|exists:shifts,id',
            'hire_date'        => 'nullable|date',
            // خطوة 3 — اختيارية
            'basic_salary'        => 'nullable|numeric|min:0',
            'housing_allowance'   => 'nullable|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'phone_allowance'     => 'nullable|numeric|min:0',
            'other_allowances'    => 'nullable|numeric|min:0',
            'commission_rate'     => 'nullable|numeric|min:0|max:100',
            'min_sales_target'    => 'nullable|numeric|min:0',
            // الصلاحيات
            'permission_group_id' => 'nullable|exists:permission_groups,id',
        ], [
            // المعلومات الأساسية
            'full_name.required'          => 'الاسم الكامل مطلوب',
            'full_name.max'               => 'الاسم لا يتجاوز 255 حرفاً',
            'national_id.required'        => 'رقم الهوية مطلوب',
            'national_id.unique'          => 'رقم الهوية مسجّل مسبقاً لموظف آخر',
            'phone.required'              => 'رقم الهاتف مطلوب',
            'phone.unique'                => 'رقم الهاتف مسجّل مسبقاً لموظف آخر',
            'email.required'              => 'البريد الإلكتروني مطلوب',
            'email.email'                 => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.unique'                => 'البريد الإلكتروني مسجّل مسبقاً لموظف آخر',
            'password.required'           => 'كلمة المرور مطلوبة',
            'password.min'                => 'كلمة المرور يجب أن لا تقل عن 8 أحرف',
            'address.required'            => 'العنوان مطلوب',
            'marital_status.required'     => 'الحالة الاجتماعية مطلوبة',
            'marital_status.in'           => 'الحالة الاجتماعية يجب أن تكون: أعزب، متزوج، مطلق، أو أرمل',
            // المعلومات الوظيفية
            'department_id.exists'        => 'القسم المختار غير موجود',
            'job_title.string'            => 'المسمى الوظيفي يجب أن يكون نصاً',
            'contract_type_id.exists'     => 'نوع التعاقد المختار غير موجود',
            'shift_id.exists'             => 'الشفت المختار غير موجود',
            'hire_date.date'              => 'تاريخ التعيين غير صحيح',
            // المعلومات المالية
            'basic_salary.numeric'        => 'الراتب الأساسي يجب أن يكون رقماً',
            'basic_salary.min'            => 'الراتب الأساسي لا يمكن أن يكون سالباً',
            'housing_allowance.numeric'   => 'بدل السكن يجب أن يكون رقماً',
            'housing_allowance.min'       => 'بدل السكن لا يمكن أن يكون سالباً',
            'transport_allowance.numeric' => 'بدل المواصلات يجب أن يكون رقماً',
            'transport_allowance.min'     => 'بدل المواصلات لا يمكن أن يكون سالباً',
            'phone_allowance.numeric'     => 'بدل الاتصالات يجب أن يكون رقماً',
            'phone_allowance.min'         => 'بدل الاتصالات لا يمكن أن يكون سالباً',
            'other_allowances.numeric'    => 'البدلات الأخرى يجب أن تكون رقماً',
            'other_allowances.min'        => 'البدلات الأخرى لا يمكن أن تكون سالبة',
            'commission_rate.numeric'     => 'نسبة العمولة يجب أن تكون رقماً',
            'commission_rate.min'         => 'نسبة العمولة لا يمكن أن تكون سالبة',
            'commission_rate.max'         => 'نسبة العمولة لا يمكن أن تتجاوز 100%',
            'min_sales_target.numeric'    => 'هدف المبيعات يجب أن يكون رقماً',
            'min_sales_target.min'        => 'هدف المبيعات لا يمكن أن يكون سالباً',
            // الصلاحيات
            'permission_group_id.exists'  => 'مجموعة الصلاحيات المختارة غير موجودة',
        ]);

        $data['password'] = Hash::make($data['password']);

        // الحقول المالية غير nullable بقاعدة البيانات (قيمتها الافتراضية 0) —
        // لو انبعتت فاضية بتتحول null من Laravel وبترفضها قاعدة البيانات صراحة
        foreach (['basic_salary', 'housing_allowance', 'transport_allowance', 'phone_allowance', 'other_allowances', 'commission_rate'] as $field) {
            $data[$field] = $data[$field] ?? 0;
        }

        $employee = Employee::create($data);

        // تعيين مجموعة الصلاحيات
        if (!empty($data['permission_group_id'])) {
            $employee->permissionGroups()->attach($data['permission_group_id']);
        }

        // إنشاء رصيد الإجازات للسنة الحالية
        $this->initLeaveBalances($employee->id);

        NotificationLog::send(
            $request->user()->id,
            'تم إضافة موظف جديد',
            "تم إضافة الموظف {$employee->full_name} برقم {$employee->employee_number}",
            'نجاح'
        );

        return $this->success($employee->load('department'), 'تم إضافة الموظف بنجاح', 201);
    }

    // PUT /api/employees/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            // مطلوبة دائماً
            'full_name'        => 'required|string|max:255',
            'phone'            => 'required|string|unique:employees,phone,' . $id,
            'email'            => 'required|email|unique:employees,email,' . $id,
            'address'          => 'required|string',
            'marital_status'   => 'required|in:أعزب,متزوج,مطلق,أرمل',
            // اختيارية
            'status'           => 'nullable|in:نشط,إيقاف مؤقت,منتهي',
            'department_id'    => 'nullable|exists:departments,id',
            'job_title'        => 'nullable|string',
            'contract_type_id' => 'nullable|exists:contract_types,id',
            'shift_id'         => 'nullable|exists:shifts,id',
            'hire_date'        => 'nullable|date',
        ], [
            'full_name.required'      => 'الاسم الكامل مطلوب',
            'full_name.max'           => 'الاسم لا يتجاوز 255 حرفاً',
            'phone.required'          => 'رقم الهاتف مطلوب',
            'phone.unique'            => 'رقم الهاتف مسجّل مسبقاً لموظف آخر',
            'email.required'          => 'البريد الإلكتروني مطلوب',
            'email.email'             => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.unique'            => 'البريد الإلكتروني مسجّل مسبقاً لموظف آخر',
            'address.required'        => 'العنوان مطلوب',
            'marital_status.required' => 'الحالة الاجتماعية مطلوبة',
            'marital_status.in'       => 'الحالة الاجتماعية يجب أن تكون: أعزب، متزوج، مطلق، أو أرمل',
            'hire_date.date'          => 'تاريخ التعيين غير صحيح',
        ]);

        $employee->update($data);

        NotificationLog::send(
            $request->user()->id,
            'تحديث بيانات موظف',
            "تم تحديث بيانات الموظف {$employee->full_name}",
            'معلومة'
        );

        return $this->success($employee->fresh('department'), 'تم تحديث بيانات الموظف');
    }

    // PATCH /api/employees/{id}/suspend — إيقاف مؤقت
    public function suspend(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['status' => 'إيقاف مؤقت']);
        return $this->success(null, 'تم إيقاف الموظف مؤقتاً');
    }

    // PATCH /api/employees/{id}/activate
    public function activate(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['status' => 'نشط']);
        return $this->success(null, 'تم تفعيل الموظف');
    }

    // POST /api/employees/{id}/salary — تعديل الراتب
    public function updateSalary(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'new_salary'     => 'required|numeric|min:0',
            'reason'         => 'required|string',
            'effective_date' => 'required|date',
        ], [
            'new_salary.required'     => 'الراتب الجديد مطلوب',
            'new_salary.numeric'      => 'الراتب يجب أن يكون رقماً',
            'new_salary.min'          => 'الراتب لا يمكن أن يكون سالباً',
            'reason.required'         => 'سبب التعديل مطلوب',
            'effective_date.required' => 'تاريخ التطبيق مطلوب',
            'effective_date.date'     => 'تاريخ التطبيق غير صحيح',
        ]);

        // حفظ السجل القديم
        SalaryHistory::create([
            'employee_id'    => $id,
            'old_salary'     => $employee->basic_salary,
            'new_salary'     => $request->new_salary,
            'reason'         => $request->reason,
            'approved_by'    => $request->user()->id,
            'effective_date' => $request->effective_date,
        ]);

        $employee->update(['basic_salary' => $request->new_salary]);

        // إشعار الموظف
        NotificationLog::send(
            $id,
            'تم تحديث راتبك',
            "تم تعديل راتبك الأساسي إلى {$request->new_salary} ريال",
            'معلومة'
        );

        return $this->success([
            'new_salary'    => $employee->fresh()->basic_salary,
            'total_salary'  => $employee->fresh()->total_salary,
        ], 'تم تحديث الراتب بنجاح');
    }

    // POST /api/employees/{id}/documents
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        Employee::findOrFail($id);

        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'type' => 'required|in:سيرة ذاتية,شهادات,هوية,جواز سفر,عقد وظيفي,أخرى',
            'name' => 'required|string',
        ], [
            'file.required' => 'يرجى اختيار ملف',
            'file.file'     => 'الملف المرفوع غير صحيح',
            'file.mimes'    => 'نوع الملف غير مدعوم، المسموح: PDF, Word, صور',
            'file.max'      => 'حجم الملف يتجاوز الحد المسموح (10 ميجابايت)',
            'type.required' => 'نوع المستند مطلوب',
            'type.in'       => 'نوع المستند غير صحيح',
            'name.required' => 'اسم المستند مطلوب',
        ]);

        $file = $request->file('file');
        $path = $file->store("employees/{$id}/documents", 'public');

        $doc = EmployeeDocument::create([
            'employee_id' => $id,
            'type'        => $request->type,
            'name'        => $request->name,
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),
        ]);

        return $this->success($doc, 'تم رفع المستند بنجاح', 201);
    }

    // DELETE /api/employees/{id}/documents/{docId}
    public function deleteDocument(int $id, int $docId): JsonResponse
    {
        $doc = EmployeeDocument::where('employee_id', $id)->findOrFail($docId);
        Storage::disk('public')->delete($doc->file_path);
        $doc->delete();
        return $this->success(null, 'تم حذف المستند');
    }

    // GET /api/employees/{id}/leave-balances
    public function leaveBalances(int $id): JsonResponse
    {
        $balances = LeaveBalance::with('leaveType')
            ->where('employee_id', $id)
            ->where('year', now()->year)
            ->get()
            ->map(fn($b) => [
                'type'           => $b->leaveType->name,
                'total_days'     => $b->total_days === 999 ? '∞' : $b->total_days,
                'used_days'      => $b->used_days,
                'remaining_days' => $b->remaining_days === 999 ? '∞' : $b->remaining_days,
                'usage_pct'      => $b->usage_percentage,
                'is_low'         => $b->isLow(),
            ]);

        return $this->success($balances);
    }

    // ---- Helper ----
    private function initLeaveBalances(int $employeeId): void
    {
        $leaveTypes = \App\Models\LeaveType::where('is_active', true)->get();
        foreach ($leaveTypes as $lt) {
            LeaveBalance::firstOrCreate(
                ['employee_id' => $employeeId, 'leave_type_id' => $lt->id, 'year' => now()->year],
                ['total_days' => $lt->total_days, 'used_days' => 0]
            );
        }
    }
}
