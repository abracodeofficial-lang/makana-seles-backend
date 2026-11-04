<?php

namespace App\Http\Controllers\API;

use App\Models\{
    City, ContactList, Employee, EmployeeDocument, Lead, LeaveType, Neighborhood, NotificationLog, PropertyImage,
    OperationPropertyTypeAssignment, Owner, PermissionDuration, PermissionGroup, Property, PropertyDocument,
    PropertyType, Shift, WaCampaign
};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// صفحة المحذوفات — للأدمن فقط: عرض السجلات المحذوفة واسترجاعها
class TrashController extends BaseController
{
    private function types(): array
    {
        $join = fn(...$parts) => implode(' · ', array_filter($parts));

        return [
            'properties' => ['label' => 'العقارات', 'model' => Property::class,
                'row' => fn($m) => [$m->name ?: 'بدون اسم', $m->property_code]],
            'owners' => ['label' => 'الملاك', 'model' => Owner::class,
                'row' => fn($m) => [$m->name ?: 'بدون اسم', $join($m->owner_code, $m->phone)]],
            'leads' => ['label' => 'المهتمون', 'model' => Lead::class,
                'row' => fn($m) => [$m->name ?: 'بدون اسم', $join($m->lead_code, $m->phone)]],
            'property_documents' => ['label' => 'مستندات العقارات', 'model' => PropertyDocument::class,
                'row' => fn($m) => [$m->file_name, Property::withTrashed()->whereKey($m->property_id)->value('property_code')]],
            'property_images' => ['label' => 'صور العقارات', 'model' => PropertyImage::class,
                'row' => fn($m) => [$m->file_name, Property::withTrashed()->whereKey($m->property_id)->value('property_code')]],
            'employee_documents' => ['label' => 'مستندات الموظفين', 'model' => EmployeeDocument::class,
                'row' => fn($m) => [$m->name ?: $m->file_name, Employee::withTrashed()->whereKey($m->employee_id)->value('full_name')]],
            'contact_lists' => ['label' => 'قوائم الأرقام', 'model' => ContactList::class,
                'row' => fn($m) => [$m->name, $m->audience]],
            'wa_campaigns' => ['label' => 'حملات واتساب', 'model' => WaCampaign::class,
                'row' => fn($m) => [$m->title, $m->audience]],
            'cities' => ['label' => 'المدن', 'model' => City::class,
                'row' => fn($m) => [$m->name, $m->code]],
            'neighborhoods' => ['label' => 'الأحياء', 'model' => Neighborhood::class,
                'row' => fn($m) => [$m->name, $m->city?->name]],
            'property_types' => ['label' => 'أنواع العقارات', 'model' => PropertyType::class,
                'row' => fn($m) => [$m->name, null]],
            'operation_assignments' => ['label' => 'تخصيص الأوبريشن', 'model' => OperationPropertyTypeAssignment::class,
                'row' => fn($m) => [$m->employee?->full_name ?: '—', $m->propertyType?->name]],
            'shifts' => ['label' => 'الورديات', 'model' => Shift::class,
                'row' => fn($m) => [$m->name, $join($m->start_time, $m->end_time)]],
            'leave_types' => ['label' => 'أنواع الإجازات', 'model' => LeaveType::class,
                'row' => fn($m) => [$m->name, null]],
            'permission_durations' => ['label' => 'مدد الإذونات', 'model' => PermissionDuration::class,
                'row' => fn($m) => [$m->name, null]],
            'permission_groups' => ['label' => 'مجموعات الصلاحيات', 'model' => PermissionGroup::class,
                'row' => fn($m) => [$m->name, $m->description]],
            'notifications' => ['label' => 'الإشعارات', 'model' => NotificationLog::class,
                'row' => fn($m) => [$m->title, $m->employee?->full_name]],
        ];
    }

    // GET /api/trash/summary — عدد المحذوفات بكل نوع
    public function summary(Request $request): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $types = collect($this->types())->map(fn($t, $key) => [
            'key'   => $key,
            'label' => $t['label'],
            'count' => $t['model']::onlyTrashed()->count(),
        ])->values();

        return $this->success($types);
    }

    // GET /api/trash?type=properties&page=1
    public function index(Request $request): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $type = $this->types()[$request->query('type')] ?? null;
        if (!$type) return $this->error('نوع غير معروف', 422);

        $page  = $type['model']::onlyTrashed()->latest('deleted_at')->paginate(20);
        $names = Employee::withTrashed()->whereIn('id', $page->pluck('deleted_by')->filter())->pluck('full_name', 'id');

        return response()->json([
            'status' => true,
            'data'   => $page->getCollection()->map(function ($m) use ($type, $names) {
                [$title, $subtitle] = $type['row']($m);
                return [
                    'id'         => $m->id,
                    'title'      => $title,
                    'subtitle'   => $subtitle,
                    'deleted_at' => $m->deleted_at,
                    'deleted_by' => $names[$m->deleted_by] ?? null,
                ];
            }),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
        ]);
    }

    // POST /api/trash/{type}/{id}/restore
    public function restore(Request $request, string $type, int $id): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $def = $this->types()[$type] ?? null;
        if (!$def) return $this->error('نوع غير معروف', 422);

        $record = $def['model']::onlyTrashed()->findOrFail($id);

        if ($record instanceof PermissionGroup && PermissionGroup::where('name', $record->name)->exists()) {
            return $this->error('في مجموعة صلاحيات بنفس الاسم — غيّر اسمها أول', 422);
        }

        $record->restore();
        return $this->success(null, 'تم الاسترجاع');
    }

    private function adminOnly(Request $request): ?JsonResponse
    {
        return $request->user()->isAdmin() ? null : $this->error('هذه الصفحة للأدمن فقط', 403);
    }
}
