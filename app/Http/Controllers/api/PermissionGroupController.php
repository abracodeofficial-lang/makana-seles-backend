<?php

namespace App\Http\Controllers\API;

use App\Models\PermissionGroup;
use App\Support\PermissionPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionGroupController extends BaseController
{
    // GET /api/permission-groups
    public function index(Request $request): JsonResponse
    {
        if ($err = $this->authorizeAction($request, 'view')) return $err;

        $groups = PermissionGroup::withCount('employees')->orderBy('name')->get();
        return $this->success($groups);
    }

    // GET /api/permission-groups/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeAction($request, 'view')) return $err;

        $group    = PermissionGroup::with('pages')->findOrFail($id);
        $existing = $group->pages->keyBy('page_key');

        return $this->success([
            'id'          => $group->id,
            'name'        => $group->name,
            'description' => $group->description,
            'pages'       => $this->buildPagesGrid($existing),
        ]);
    }

    // POST /api/permission-groups
    public function store(Request $request): JsonResponse
    {
        if ($err = $this->authorizeAction($request, 'add')) return $err;

        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:permission_groups,name',
            'description' => 'nullable|string',
            'pages'       => 'nullable|array',
        ], [
            'name.required' => 'اسم المجموعة مطلوب',
            'name.unique'   => 'يوجد مجموعة بنفس الاسم مسبقاً',
        ]);

        $group = PermissionGroup::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->savePages($group, $request->input('pages', []));

        return $this->success($group->load('pages'), 'تم إنشاء المجموعة بنجاح', 201);
    }

    // PUT /api/permission-groups/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeAction($request, 'edit')) return $err;

        $group = PermissionGroup::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:permission_groups,name,' . $id,
            'description' => 'nullable|string',
            'pages'       => 'nullable|array',
        ], [
            'name.required' => 'اسم المجموعة مطلوب',
            'name.unique'   => 'يوجد مجموعة بنفس الاسم مسبقاً',
        ]);

        $group->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $this->savePages($group, $request->input('pages', []));

        return $this->success($group->load('pages'), 'تم تحديث المجموعة بنجاح');
    }

    // DELETE /api/permission-groups/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeAction($request, 'delete')) return $err;

        $group = PermissionGroup::withCount('employees')->findOrFail($id);

        if ($group->employees_count > 0) {
            return $this->error('لا يمكن حذف المجموعة لوجود موظفين مرتبطين بها، انقلهم لمجموعة أخرى أولاً', 422);
        }

        $group->delete();
        return $this->success(null, 'تم حذف المجموعة');
    }

    // ---- Helpers ----
    private function authorizeAction(Request $request, string $action): ?JsonResponse
    {
        if (!$request->user()->hasPermission('roles', $action)) {
            return $this->error('ليس لديك صلاحية للوصول لهذه الصفحة', 403);
        }
        return null;
    }

    private function buildPagesGrid($existing): array
    {
        return collect(PermissionPages::all())->map(function ($name, $key) use ($existing) {
            $p = $existing->get($key);
            return [
                'page_key'    => $key,
                'page_name'   => $name,
                'can_view'    => (bool) ($p->can_view    ?? false),
                'can_add'     => (bool) ($p->can_add     ?? false),
                'can_edit'    => (bool) ($p->can_edit    ?? false),
                'can_delete'  => (bool) ($p->can_delete  ?? false),
                'can_export'  => (bool) ($p->can_export  ?? false),
                'can_approve' => (bool) ($p->can_approve ?? false),
            ];
        })->values()->toArray();
    }

    private function savePages(PermissionGroup $group, array $pages): void
    {
        $byKey = collect($pages)->keyBy('page_key');

        foreach (PermissionPages::all() as $key => $name) {
            $p = $byKey->get($key, []);
            $group->pages()->updateOrCreate(
                ['page_key' => $key],
                [
                    'page_name'   => $name,
                    'can_view'    => (bool) ($p['can_view']    ?? false),
                    'can_add'     => (bool) ($p['can_add']     ?? false),
                    'can_edit'    => (bool) ($p['can_edit']    ?? false),
                    'can_delete'  => (bool) ($p['can_delete']  ?? false),
                    'can_export'  => (bool) ($p['can_export']  ?? false),
                    'can_approve' => (bool) ($p['can_approve'] ?? false),
                ]
            );
        }
    }
}
