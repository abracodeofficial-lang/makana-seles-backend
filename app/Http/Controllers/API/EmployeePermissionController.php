<?php

namespace App\Http\Controllers\API;

use App\Models\Employee;
use App\Support\PermissionPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeePermissionController extends BaseController
{
    // PATCH /api/employees/{id}/permission-group
    public function updateGroup(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeAction($request, 'edit')) return $err;

        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'permission_group_id' => 'nullable|exists:permission_groups,id',
        ], [
            'permission_group_id.exists' => 'مجموعة الصلاحيات المختارة غير موجودة',
        ]);

        $employee->permissionGroups()->sync(
            !empty($data['permission_group_id']) ? [$data['permission_group_id']] : []
        );

        return $this->success(null, 'تم تحديث مجموعة صلاحيات الموظف');
    }

    // GET /api/employees/{id}/permissions
    public function show(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeAction($request, 'view')) return $err;

        $employee = Employee::with(['permissionGroups.pages', 'permissionOverrides'])->findOrFail($id);

        $groupPages = $employee->permissionGroups->flatMap(fn($g) => $g->pages)->groupBy('page_key');
        $overrides  = $employee->permissionOverrides->keyBy('page_key');

        $pages = collect(PermissionPages::all())->map(function ($name, $key) use ($groupPages, $overrides) {
            $group = $groupPages->get($key, collect());
            $ov    = $overrides->get($key);

            return [
                'page_key'  => $key,
                'page_name' => $name,
                'group'     => [
                    'view'    => $group->contains('can_view', true),
                    'add'     => $group->contains('can_add', true),
                    'edit'    => $group->contains('can_edit', true),
                    'delete'  => $group->contains('can_delete', true),
                    'export'  => $group->contains('can_export', true),
                    'approve' => $group->contains('can_approve', true),
                ],
                'override'  => [
                    'view'    => $ov?->can_view,
                    'add'     => $ov?->can_add,
                    'edit'    => $ov?->can_edit,
                    'delete'  => $ov?->can_delete,
                    'export'  => $ov?->can_export,
                    'approve' => $ov?->can_approve,
                ],
            ];
        })->values();

        return $this->success([
            'permission_group_id' => $employee->permissionGroups->first()?->id,
            'pages'                => $pages,
        ]);
    }

    // PUT /api/employees/{id}/permissions
    public function update(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeAction($request, 'edit')) return $err;

        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'pages'               => 'required|array',
            'pages.*.page_key'    => 'required|string',
            'pages.*.can_view'    => 'nullable|boolean',
            'pages.*.can_add'     => 'nullable|boolean',
            'pages.*.can_edit'    => 'nullable|boolean',
            'pages.*.can_delete'  => 'nullable|boolean',
            'pages.*.can_export'  => 'nullable|boolean',
            'pages.*.can_approve' => 'nullable|boolean',
        ]);

        foreach ($data['pages'] as $p) {
            $employee->permissionOverrides()->updateOrCreate(
                ['page_key' => $p['page_key']],
                [
                    'can_view'    => $p['can_view']    ?? null,
                    'can_add'     => $p['can_add']     ?? null,
                    'can_edit'    => $p['can_edit']    ?? null,
                    'can_delete'  => $p['can_delete']  ?? null,
                    'can_export'  => $p['can_export']  ?? null,
                    'can_approve' => $p['can_approve'] ?? null,
                ]
            );
        }

        return $this->success(null, 'تم تحديث صلاحيات الموظف');
    }

    private function authorizeAction(Request $request, string $action): ?JsonResponse
    {
        if (!$request->user()->hasPermission('roles', $action)) {
            return $this->error('ليس لديك صلاحية للوصول لهذه الصفحة', 403);
        }
        return null;
    }
}
