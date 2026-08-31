<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\API\{
    AuthController,
    OwnerController,
    PropertyController,
    LeadController,
    VisitController,
    EmployeeController,
    AttendanceController,
    PunchController,
    LeaveRequestController,
    PermissionRequestController,
    PermissionGroupController,
    EmployeePermissionController,
    NotificationController,
    LookupController,
};
use App\Models\{City, Neighborhood, Department, ContractType, Shift};

// ============================================================
// AUTH — بدون توثيق
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ============================================================
// LOOKUP — بيانات القوائم (بدون توثيق)
// ============================================================
Route::prefix('lookup')->group(function () {
    Route::get('cities', function () {
        return response()->json(City::orderBy('name')->get(['id', 'name']));
    });
    Route::get('neighborhoods', function (Request $request) {
        return response()->json(
            Neighborhood::where('city_id', $request->city_id)->orderBy('name')->get(['id', 'name'])
        );
    });
    Route::get('hr', function () {
        return response()->json([
            'departments'    => Department::where('is_active', true)->get(['id', 'name']),
            'contract_types' => ContractType::get(['id', 'name']),
            'shifts'         => Shift::get(['id', 'name']),
        ]);
    });
});

// ============================================================
// كل الروتات التالية تحتاج Sanctum Token
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('me',              [AuthController::class, 'me']);
        Route::post('logout',         [AuthController::class, 'logout']);
        Route::post('change-password',[AuthController::class, 'changePassword']);
    });

    // ── SALES MODULE ────────────────────────────────────────

    // الملاك
    Route::prefix('owners')->group(function () {
        Route::get('/',                [OwnerController::class, 'index']);
        Route::post('/',               [OwnerController::class, 'store']);
        Route::get('{id}',             [OwnerController::class, 'show']);
        Route::put('{id}',             [OwnerController::class, 'update']);
        Route::delete('{id}',          [OwnerController::class, 'destroy']);
        Route::get('{id}/properties',  [OwnerController::class, 'properties']);
    });

    // العقارات
    Route::prefix('properties')->group(function () {
        Route::get('/',                             [PropertyController::class, 'index']);
        Route::post('/',                            [PropertyController::class, 'store']);
        Route::get('{id}',                          [PropertyController::class, 'show']);
        Route::put('{id}',                          [PropertyController::class, 'update']);
        Route::delete('{id}',                       [PropertyController::class, 'destroy']);
        Route::post('{id}/documents',               [PropertyController::class, 'uploadDocument']);
        Route::delete('{id}/documents/{docId}',     [PropertyController::class, 'deleteDocument']);
    });

    // المهتمون
    Route::prefix('leads')->group(function () {
        Route::get('/',              [LeadController::class, 'index']);
        Route::post('/',             [LeadController::class, 'store']);
        Route::get('{id}',           [LeadController::class, 'show']);
        Route::put('{id}',           [LeadController::class, 'update']);
        Route::delete('{id}',        [LeadController::class, 'destroy']);
        Route::patch('{id}/status',  [LeadController::class, 'updateStatus']);
    });

    // الزيارات
    Route::prefix('visits')->group(function () {
        Route::get('/',              [VisitController::class, 'index']);
        Route::post('/',             [VisitController::class, 'store']);
        Route::patch('{id}/confirm', [VisitController::class, 'confirm']);
        Route::patch('{id}/cancel',  [VisitController::class, 'cancel']);
    });

    // ── HR MODULE ────────────────────────────────────────────

    // الموظفون
    Route::prefix('employees')->group(function () {
        Route::get('/',                         [EmployeeController::class, 'index']);
        Route::post('/',                        [EmployeeController::class, 'store']);
        Route::get('{id}',                      [EmployeeController::class, 'show']);
        Route::put('{id}',                      [EmployeeController::class, 'update']);
        Route::patch('{id}/suspend',            [EmployeeController::class, 'suspend']);
        Route::patch('{id}/activate',           [EmployeeController::class, 'activate']);
        Route::post('{id}/salary',              [EmployeeController::class, 'updateSalary']);
        Route::post('{id}/documents',           [EmployeeController::class, 'uploadDocument']);
        Route::delete('{id}/documents/{docId}', [EmployeeController::class, 'deleteDocument']);
        Route::get('{id}/leave-balances',       [EmployeeController::class, 'leaveBalances']);
        Route::patch('{id}/permission-group',   [EmployeePermissionController::class, 'updateGroup']);
        Route::get('{id}/permissions',          [EmployeePermissionController::class, 'show']);
        Route::put('{id}/permissions',          [EmployeePermissionController::class, 'update']);
    });

    // مجموعات الصلاحيات (الأدوار)
    Route::prefix('permission-groups')->group(function () {
        Route::get('/',       [PermissionGroupController::class, 'index']);
        Route::post('/',      [PermissionGroupController::class, 'store']);
        Route::get('{id}',    [PermissionGroupController::class, 'show']);
        Route::put('{id}',    [PermissionGroupController::class, 'update']);
        Route::delete('{id}', [PermissionGroupController::class, 'destroy']);
    });

    // الحضور والانصراف (إدارة)
    Route::prefix('attendance')->group(function () {
        Route::get('/',             [AttendanceController::class, 'index']);
        Route::post('/',            [AttendanceController::class, 'store']);
        Route::get('export',        [AttendanceController::class, 'export']);
        Route::patch('{id}/approve',[AttendanceController::class, 'approve']);
    });

    // بصمة الموظف (self-service)
    Route::prefix('punch')->group(function () {
        Route::get('today',        [PunchController::class, 'today']);
        Route::post('check-in',    [PunchController::class, 'checkIn']);
        Route::post('check-out',   [PunchController::class, 'checkOut']);
        Route::post('break-start', [PunchController::class, 'breakStart']);
        Route::post('break-end',   [PunchController::class, 'breakEnd']);
        Route::get('history',      [PunchController::class, 'history']);
    });

    // الإجازات
    Route::prefix('leave-requests')->group(function () {
        Route::get('/',                    [LeaveRequestController::class, 'index']);
        Route::post('/',                   [LeaveRequestController::class, 'store']);
        Route::patch('{id}/manager-approve',[LeaveRequestController::class, 'managerApprove']);
        Route::patch('{id}/manager-reject', [LeaveRequestController::class, 'managerReject']);
        Route::patch('{id}/manager-inquire',[LeaveRequestController::class, 'managerInquire']);
        Route::patch('{id}/hr-approve',    [LeaveRequestController::class, 'hrApprove']);
        Route::patch('{id}/hr-reject',     [LeaveRequestController::class, 'hrReject']);
        Route::patch('{id}/hr-inquire',    [LeaveRequestController::class, 'hrInquire']);
        Route::patch('{id}/clarify',       [LeaveRequestController::class, 'clarify']);
    });

    // الإذونات
    Route::prefix('permission-requests')->group(function () {
        Route::get('/',             [PermissionRequestController::class, 'index']);
        Route::post('/',            [PermissionRequestController::class, 'store']);
        Route::patch('{id}/approve', [PermissionRequestController::class, 'approve']);
        Route::patch('{id}/reject',  [PermissionRequestController::class, 'reject']);
        Route::patch('{id}/inquire', [PermissionRequestController::class, 'inquire']);
        Route::patch('{id}/clarify', [PermissionRequestController::class, 'clarify']);
    });

    // الإشعارات
    Route::prefix('notifications')->group(function () {
        Route::get('/',             [NotificationController::class, 'index']);
        Route::patch('read-all',    [NotificationController::class, 'markAllRead']);
        Route::delete('clear-all',  [NotificationController::class, 'clearAll']);
        Route::patch('{id}/read',   [NotificationController::class, 'markRead']);
        Route::delete('{id}',       [NotificationController::class, 'destroy']);
    });

    // ── LOOKUPS (CRUD) ───────────────────────────────────────
    Route::prefix('lookups')->group(function () {
        // المدن
        Route::get('cities',             [LookupController::class, 'citiesIndex']);
        Route::post('cities',            [LookupController::class, 'citiesStore']);
        Route::put('cities/{id}',        [LookupController::class, 'citiesUpdate']);
        Route::delete('cities/{id}',     [LookupController::class, 'citiesDestroy']);

        // الأحياء
        Route::get('cities/{cityId}/neighborhoods', [LookupController::class, 'neighborhoodsIndex']);
        Route::post('neighborhoods',                [LookupController::class, 'neighborhoodsStore']);
        Route::put('neighborhoods/{id}',            [LookupController::class, 'neighborhoodsUpdate']);
        Route::delete('neighborhoods/{id}',         [LookupController::class, 'neighborhoodsDestroy']);

        // أنواع العقارات
        Route::get('property-types',         [LookupController::class, 'propertyTypesIndex']);
        Route::post('property-types',        [LookupController::class, 'propertyTypesStore']);
        Route::put('property-types/{id}',    [LookupController::class, 'propertyTypesUpdate']);
        Route::delete('property-types/{id}', [LookupController::class, 'propertyTypesDestroy']);

        // الشفتات
        Route::get('shifts',         [LookupController::class, 'shiftsIndex']);
        Route::post('shifts',        [LookupController::class, 'shiftsStore']);
        Route::put('shifts/{id}',    [LookupController::class, 'shiftsUpdate']);
        Route::delete('shifts/{id}', [LookupController::class, 'shiftsDestroy']);

        // أنواع الإجازات
        Route::get('leave-types',         [LookupController::class, 'leaveTypesIndex']);
        Route::post('leave-types',        [LookupController::class, 'leaveTypesStore']);
        Route::put('leave-types/{id}',    [LookupController::class, 'leaveTypesUpdate']);
        Route::delete('leave-types/{id}', [LookupController::class, 'leaveTypesDestroy']);

        // إعدادات الدوام
        Route::get('attendance-settings', [LookupController::class, 'attendanceSettingsShow']);
        Route::put('attendance-settings', [LookupController::class, 'attendanceSettingsUpdate']);

        // مدد الإذونات
        Route::get('permission-durations',         [LookupController::class, 'permissionDurationsIndex']);
        Route::post('permission-durations',        [LookupController::class, 'permissionDurationsStore']);
        Route::put('permission-durations/{id}',    [LookupController::class, 'permissionDurationsUpdate']);
        Route::delete('permission-durations/{id}', [LookupController::class, 'permissionDurationsDestroy']);
    });
});
