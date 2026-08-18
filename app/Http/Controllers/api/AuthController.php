<?php

namespace App\Http\Controllers\API;

use App\Models\Employee;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    // POST /api/auth/login
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $employee = Employee::where('email', $request->email)->first();

        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return $this->error('البريد الإلكتروني أو كلمة المرور غير صحيحة', 401);
        }

        if ($employee->status !== 'نشط') {
            return $this->error('الحساب موقوف، تواصل مع الإدارة', 403);
        }

        $token = $employee->createToken('api-token')->plainTextToken;

        // جلب صلاحيات الموظف
        $permissions = $this->getPermissions($employee);

        return $this->success([
            'token'       => $token,
            'employee'    => [
                'id'              => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name'       => $employee->full_name,
                'email'           => $employee->email,
                'job_title'       => $employee->job_title,
                'department'      => $employee->department?->name,
                'status'          => $employee->status,
            ],
            'permissions' => $permissions,
        ], 'تم تسجيل الدخول بنجاح');
    }

    // POST /api/auth/logout
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success(null, 'تم تسجيل الخروج بنجاح');
    }

    // GET /api/auth/me
    public function me(Request $request): JsonResponse
    {
        $employee = $request->user()->load(['department', 'shift']);
        $permissions = $this->getPermissions($employee);

        return $this->success([
            'employee'    => $employee,
            'permissions' => $permissions,
            'unread_notifications' => NotificationLog::where('employee_id', $employee->id)
                ->where('is_read', false)->count(),
        ]);
    }

    // POST /api/auth/change-password
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed',
        ]);

        $employee = $request->user();

        if (!Hash::check($request->current_password, $employee->password)) {
            return $this->error('كلمة المرور الحالية غير صحيحة', 422);
        }

        $employee->update(['password' => Hash::make($request->new_password)]);

        return $this->success(null, 'تم تغيير كلمة المرور بنجاح');
    }

    // ---- Helper ----
    private function getPermissions(Employee $employee): array
    {
        $groupPerms = $employee->permissionGroups()
            ->with('pages')
            ->get()
            ->flatMap(fn($group) => $group->pages)
            ->groupBy('page_key')
            ->map(fn($pages) => [
                'view'    => $pages->contains('can_view', true),
                'add'     => $pages->contains('can_add', true),
                'edit'    => $pages->contains('can_edit', true),
                'delete'  => $pages->contains('can_delete', true),
                'export'  => $pages->contains('can_export', true),
                'approve' => $pages->contains('can_approve', true),
            ]);

        // الاستثناءات الفردية تتجاوز صلاحيات المجموعة لكل صفحة/عملية على حدة
        $overrides = $employee->permissionOverrides()->get()->keyBy('page_key');
        $pageKeys  = $groupPerms->keys()->merge($overrides->keys())->unique();

        return $pageKeys->mapWithKeys(function ($key) use ($groupPerms, $overrides) {
            $base = $groupPerms->get($key, [
                'view' => false, 'add' => false, 'edit' => false,
                'delete' => false, 'export' => false, 'approve' => false,
            ]);

            $override = $overrides->get($key);
            if ($override) {
                foreach (['view', 'add', 'edit', 'delete', 'export', 'approve'] as $action) {
                    $column = 'can_' . $action;
                    if ($override->{$column} !== null) $base[$action] = (bool) $override->{$column};
                }
            }

            return [$key => $base];
        })->toArray();
    }
}
