<?php

namespace App\Http\Controllers\API;

use App\Models\Employee;
use App\Models\NotificationLog;
use App\Models\WaOutbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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

    // استعادة كلمة المرور بكود على واتساب الموظف (عبر برنامج الواتساب بالمكتب — بدون تكلفة)
    private const RESET_CODE_MINUTES  = 10;
    private const RESET_MAX_ATTEMPTS  = 5;
    private const RESET_RESEND_SECONDS = 60;

    // POST /api/auth/forgot-password {email} — بيفحص الإيميل وبيبعت كود على واتساب الرقم المسجل
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email'], [
            'email.required' => 'اكتب البريد الإلكتروني',
            'email.email'    => 'صيغة البريد الإلكتروني غير صحيحة',
        ]);

        // حد الطلبات لكل جهاز — بيحمي من تجربة إيميلات كتير لمعرفة مين موظف
        $key = 'forgot-password:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->error('طلبات كتير، جرّب بعد ' . ceil(RateLimiter::availableIn($key) / 60) . ' دقيقة', 429);
        }
        RateLimiter::hit($key, 600);

        $employee = Employee::where('email', trim($request->email))->first();
        if (!$employee) {
            return $this->error('هاد الإيميل مش مسجل عندنا، تأكد منه أو تواصل مع الإدارة', 422);
        }
        if ($employee->status !== 'نشط') {
            return $this->error('الحساب موقوف، تواصل مع الإدارة', 422);
        }
        $phone = BroadcastController::normalizePhone($employee->phone);
        if (strlen($phone) < 11) {
            return $this->error('ما في رقم جوال صحيح مسجل لحسابك، تواصل مع الإدارة', 422);
        }

        $existing = DB::table('password_reset_tokens')->where('email', $employee->email)->first();
        if ($existing && now()->diffInSeconds($existing->created_at) < self::RESET_RESEND_SECONDS) {
            $wait = self::RESET_RESEND_SECONDS - now()->diffInSeconds($existing->created_at);
            return $this->error("بعتنالك كود قبل شوي، استنى {$wait} ثانية قبل ما تطلب كود جديد", 429);
        }

        if (!WaGatewayController::isConnected()) {
            return $this->error('خدمة الواتساب غير متصلة حالياً، تواصل مع الإدارة', 503);
        }

        $code = (string) random_int(100000, 999999);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $employee->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );
        Cache::forget($this->resetAttemptsKey($employee->email));

        // كود قديم لسا ما انبعت ما في داعي يوصل
        WaOutbox::where('phone', $phone)->where('purpose', 'password_reset')->where('status', 'pending')
            ->update(['status' => 'failed', 'error' => 'انطلب كود أحدث']);
        WaOutbox::create([
            'phone'      => $phone,
            'purpose'    => 'password_reset',
            'expires_at' => now()->addMinutes(self::RESET_CODE_MINUTES),
            'message'    => "🔐 نظام الإدارة — مكانة\n\nكود استعادة كلمة المرور: *{$code}*\n\nصالح لمدة " . self::RESET_CODE_MINUTES . " دقائق. لا تشاركه مع أي حدا.\nإذا ما طلبت الكود تجاهل الرسالة.",
        ]);

        return $this->success([
            'phone'          => '…' . substr($phone, -4),
            'expires_in'     => self::RESET_CODE_MINUTES * 60,
            'resend_after'   => self::RESET_RESEND_SECONDS,
        ], 'بعتنالك كود على الواتساب');
    }

    // POST /api/auth/reset-password {email, code, password, password_confirmation}
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'code.required'      => 'اكتب الكود اللي وصلك',
            'code.digits'        => 'الكود 6 أرقام',
            'password.required'  => 'اكتب كلمة المرور الجديدة',
            'password.min'       => 'كلمة المرور لازم تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة المرور مش مطابق',
        ]);

        $email = trim($request->email);
        $row   = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$row || now()->diffInMinutes($row->created_at) >= self::RESET_CODE_MINUTES) {
            return $this->error('الكود منتهي، اطلب كود جديد', 422);
        }

        $attemptsKey = $this->resetAttemptsKey($email);
        if (!Hash::check($request->code, $row->token)) {
            $attempts = Cache::increment($attemptsKey);
            Cache::put($attemptsKey, $attempts, now()->addMinutes(self::RESET_CODE_MINUTES));
            if ($attempts >= self::RESET_MAX_ATTEMPTS) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                Cache::forget($attemptsKey);
                return $this->error('الكود غلط وخلصت المحاولات، اطلب كود جديد', 422);
            }
            $left = self::RESET_MAX_ATTEMPTS - $attempts;
            return $this->error("الكود غلط، باقيلك {$left} محاولات", 422);
        }

        $employee = Employee::where('email', $email)->first();
        if (!$employee || $employee->status !== 'نشط') {
            return $this->error('الحساب موقوف، تواصل مع الإدارة', 422);
        }

        $employee->forceFill(['password' => Hash::make($request->password)])->save();
        $employee->tokens()->delete(); // تسجيل خروج من كل الأجهزة
        DB::table('password_reset_tokens')->where('email', $email)->delete();
        Cache::forget($attemptsKey);

        return $this->success(null, 'تم تغيير كلمة المرور، سجّل دخولك');
    }

    private function resetAttemptsKey(string $email): string
    {
        return 'reset-attempts:' . strtolower($email);
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
            'is_admin'    => $employee->isAdmin(),
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
