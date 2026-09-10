<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{City, Neighborhood, PropertyType, Shift, LeaveType, AttendanceSetting, PermissionDuration};

class LookupController extends Controller
{
    // ── Cities ───────────────────────────────────────────────

    public function citiesIndex()
    {
        return response()->json([
            'status' => true,
            'data'   => City::orderBy('name')->get(),
        ]);
    }

    public function citiesStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:10',
        ]);
        return response()->json(['status' => true, 'data' => City::create($data)], 201);
    }

    public function citiesUpdate(Request $request, int $id)
    {
        $city = City::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:10',
        ]);
        $city->update($data);
        return response()->json(['status' => true, 'data' => $city]);
    }

    public function citiesDestroy(int $id)
    {
        City::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف']);
    }

    // ── Neighborhoods ────────────────────────────────────────

    public function neighborhoodsIndex(int $cityId)
    {
        $list = Neighborhood::where('city_id', $cityId)
                    ->with('city:id,name')
                    ->orderBy('name')
                    ->get();
        return response()->json(['status' => true, 'data' => $list]);
    }

    public function neighborhoodsStore(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'city_id' => 'required|exists:cities,id',
        ]);
        $nh = Neighborhood::create($data);
        return response()->json(['status' => true, 'data' => $nh->load('city:id,name')], 201);
    }

    public function neighborhoodsUpdate(Request $request, int $id)
    {
        $nh   = Neighborhood::findOrFail($id);
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'city_id' => 'required|exists:cities,id',
        ]);
        $nh->update($data);
        return response()->json(['status' => true, 'data' => $nh->load('city:id,name')]);
    }

    public function neighborhoodsDestroy(int $id)
    {
        Neighborhood::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف']);
    }

    // ── Property Types ───────────────────────────────────────

    public function propertyTypesIndex()
    {
        return response()->json([
            'status' => true,
            'data'   => PropertyType::orderBy('name')->get(),
        ]);
    }

    public function propertyTypesStore(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        return response()->json(['status' => true, 'data' => PropertyType::create($data)], 201);
    }

    public function propertyTypesUpdate(Request $request, int $id)
    {
        $type = PropertyType::findOrFail($id);
        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);
        $type->update($data);
        return response()->json(['status' => true, 'data' => $type]);
    }

    public function propertyTypesDestroy(int $id)
    {
        PropertyType::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف']);
    }

    // ── تخصيص موظفي الأوبريشن لأنواع العقارات ──────────────────

    public function operationAssignmentsIndex()
    {
        return response()->json([
            'status' => true,
            'data'   => \App\Models\OperationPropertyTypeAssignment::with([
                'employee:id,full_name',
                'propertyType:id,name',
            ])->get(),
        ]);
    }

    public function operationAssignmentsStore(Request $request)
    {
        $data = $request->validate([
            'employee_id'      => 'required|exists:employees,id',
            'property_type_id' => 'required|exists:property_types,id',
        ]);
        $assignment = \App\Models\OperationPropertyTypeAssignment::firstOrCreate($data);
        return response()->json([
            'status' => true,
            'data'   => $assignment->load(['employee:id,full_name', 'propertyType:id,name']),
        ], 201);
    }

    public function operationAssignmentsDestroy(int $id)
    {
        \App\Models\OperationPropertyTypeAssignment::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف']);
    }

    // ── Shifts ───────────────────────────────────────────────

    public function shiftsIndex()
    {
        return response()->json([
            'status' => true,
            'data'   => Shift::orderBy('name')->get(),
        ]);
    }

    public function shiftsStore(Request $request)
    {
        $data = $request->validate([
            'name'                   => 'required|string|max:100',
            'start_time'             => 'required|date_format:H:i',
            'end_time'               => 'required|date_format:H:i',
            'late_tolerance_minutes' => 'required|integer|min:0',
        ]);
        return response()->json(['status' => true, 'data' => Shift::create($data)], 201);
    }

    public function shiftsUpdate(Request $request, int $id)
    {
        $shift = Shift::findOrFail($id);
        $data  = $request->validate([
            'name'                   => 'required|string|max:100',
            'start_time'             => 'required|date_format:H:i',
            'end_time'               => 'required|date_format:H:i',
            'late_tolerance_minutes' => 'required|integer|min:0',
        ]);
        $shift->update($data);
        return response()->json(['status' => true, 'data' => $shift]);
    }

    public function shiftsDestroy(int $id)
    {
        Shift::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف']);
    }

    // ── Leave Types ──────────────────────────────────────────

    public function leaveTypesIndex()
    {
        return response()->json([
            'status' => true,
            'data'   => LeaveType::orderBy('name')->get(),
        ]);
    }

    public function leaveTypesStore(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:100',
            'total_days'          => 'required|integer|min:1',
            'requires_attachment' => 'boolean',
            'is_active'           => 'boolean',
        ]);
        $data['is_active'] = $data['is_active'] ?? true;
        return response()->json(['status' => true, 'data' => LeaveType::create($data)], 201);
    }

    public function leaveTypesUpdate(Request $request, int $id)
    {
        $type = LeaveType::findOrFail($id);
        $data = $request->validate([
            'name'                => 'required|string|max:100',
            'total_days'          => 'required|integer|min:1',
            'requires_attachment' => 'boolean',
            'is_active'           => 'boolean',
        ]);
        $type->update($data);
        return response()->json(['status' => true, 'data' => $type]);
    }

    public function leaveTypesDestroy(int $id)
    {
        LeaveType::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف']);
    }

    // ── Permission Durations ─────────────────────────────────

    public function permissionDurationsIndex()
    {
        return response()->json([
            'status' => true,
            'data'   => PermissionDuration::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function permissionDurationsStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);
        return response()->json(['status' => true, 'data' => PermissionDuration::create($data)], 201);
    }

    public function permissionDurationsUpdate(Request $request, int $id)
    {
        $dur  = PermissionDuration::findOrFail($id);
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);
        $dur->update($data);
        return response()->json(['status' => true, 'data' => $dur]);
    }

    public function permissionDurationsDestroy(int $id)
    {
        PermissionDuration::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'تم الحذف']);
    }

    // ── Attendance Settings ───────────────────────────────────

    public function attendanceSettingsShow()
    {
        return response()->json([
            'status' => true,
            'data'   => AttendanceSetting::current(),
        ]);
    }

    public function attendanceSettingsUpdate(Request $request)
    {
        $data = $request->validate([
            'late_tolerance_minutes'    => 'required|integer|min:0',
            'overtime_multiplier'       => 'required|numeric|min:1',
            'deduction_after_late_days' => 'required|integer|min:0',
        ]);
        $setting = AttendanceSetting::current();
        $setting->update($data);
        return response()->json(['status' => true, 'data' => $setting]);
    }
}
