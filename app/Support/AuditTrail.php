<?php

namespace App\Support;

use App\Models\AuditLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// بيسجل كل إضافة/تعديل/حذف/استرجاع بيعمله موظف مسجّل دخول (من الأحداث بـ AppServiceProvider).
// العمليات بدون مستخدم (الجدولة، بوابة الواتساب) ما بتتسجل. خطأ بالتسجيل ما بيوقف العملية نفسها.
class AuditTrail
{
    // اسم كل نوع بالعربي — للعرض والفلترة
    public const TYPES = [
        'Property' => 'عقار', 'PropertyDocument' => 'مستند عقار', 'Owner' => 'مالك', 'Lead' => 'مهتم', 'Visit' => 'زيارة',
        'Employee' => 'موظف', 'EmployeeDocument' => 'مستند موظف', 'SalaryHistory' => 'تعديل راتب',
        'Attendance' => 'حضور', 'AttendanceSetting' => 'إعدادات الحضور', 'AutoLeave' => 'إجازة تلقائية',
        'LeaveRequest' => 'طلب إجازة', 'LeaveBalance' => 'رصيد إجازة', 'PermissionRequest' => 'طلب إذن',
        'City' => 'مدينة', 'Neighborhood' => 'حي', 'PropertyType' => 'نوع عقار', 'MarketingType' => 'نوع تسويق',
        'UsageType' => 'نوع استخدام', 'PaymentMethod' => 'طريقة دفع', 'Department' => 'قسم', 'ContractType' => 'نوع عقد',
        'Shift' => 'وردية', 'LeaveType' => 'نوع إجازة', 'PermissionDuration' => 'مدة إذن',
        'OperationPropertyTypeAssignment' => 'تخصيص أوبريشن',
        'PermissionGroup' => 'مجموعة صلاحيات', 'PermissionGroupPage' => 'صلاحيات صفحة', 'EmployeePermissionOverride' => 'استثناء صلاحية',
        'ContactList' => 'قائمة أرقام', 'WaCampaign' => 'حملة واتساب',
        'PropertyImage' => 'صورة عقار', 'PublishingSetting' => 'إعدادات النشر',
    ];

    // ضجيج أو تفاصيل تقنية — ما بتتسجل
    private const SKIPPED = [
        'AuditLog', 'NotificationLog', 'PushSubscription', 'WaCampaignRecipient', 'ContactListMember', 'WaGatewaySetting', 'User', 'PropertyPublication', 'PublicationLog',
    ];

    private const IGNORED_FIELDS = ['id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'created_by', 'remember_token', 'uuid', 'ready_at', 'ready_by', 'sort_order'];
    private const SECRET_FIELDS  = ['password', 'token', 'wordpress_app_password', 'bayut_token'];

    private const LABELS = [
        'name' => 'الاسم', 'full_name' => 'الاسم', 'title' => 'العنوان', 'phone' => 'الهاتف', 'whatsapp' => 'الواتساب', 'email' => 'البريد',
        'password' => 'كلمة المرور', 'status' => 'الحالة', 'notes' => 'ملاحظات', 'description' => 'الوصف', 'address' => 'العنوان', 'source' => 'المصدر',
        'type' => 'النوع', 'code' => 'الكود', 'is_active' => 'مفعّل', 'reason' => 'السبب', 'message' => 'الرسالة', 'location' => 'الموقع',
        'property_code' => 'كود العقار', 'property_type_id' => 'نوع العقار', 'marketing_type_id' => 'نوع التسويق', 'usage_type_id' => 'الاستخدام',
        'is_verified' => 'محقق', 'listed_price' => 'السعر المعروض', 'net_price' => 'السعر الصافي', 'price_per_meter' => 'سعر المتر',
        'discount_amount' => 'الخصم', 'trial_property_code' => 'كود عقار تجريبي', 'total_area' => 'المساحة (رقم)', 'total_area_text' => 'المساحة','land_area' => 'مساحة الأرض', 'building_area' => 'مساحة البناء',
        'city_id' => 'المدينة', 'neighborhood_id' => 'الحي', 'street' => 'عرض الشارع', 'street_width' => 'عرض الشارع', 'building_number' => 'رقم المبنى',
        'postal_code' => 'الرمز البريدي', 'has_brokerage_contract' => 'عقد وساطة', 'license_number' => 'رقم الترخيص', 'plan_number' => 'رقم المخطط',
        'plot_number' => 'رقم القطعة', 'direction' => 'الاتجاه', 'facade' => 'الواجهة', 'owner_id' => 'المالك', 'features' => 'الميزات', 'components' => 'المكونات',
        'owner_code' => 'كود المالك', 'exclusive_status' => 'الحصرية', 'brokerage_status' => 'الوساطة', 'auction_status' => 'المزاد',
        'sales_employee_owners_id' => 'مبيعات الملاك', 'sales_employee_leads_id' => 'مبيعات المهتمين',
        'lead_code' => 'كود المهتم', 'applicant_type' => 'صفة المتقدم', 'budget' => 'الميزانية', 'offered_price' => 'السعر المعروض', 'price_category' => 'فئة السعر',
        'classification' => 'التصنيف', 'seriousness_level' => 'نسبة الجدية', 'purchase_goal' => 'هدف الشراء', 'payment_method_id' => 'طريقة الدفع',
        'operation_employee_id' => 'موظف الأوبريشن', 'broker_employee_id' => 'الأخصائي', 'operation_status' => 'حالة الأوبريشن', 'specialist_stage' => 'مرحلة الأخصائي',
        'agreed_amount' => 'المبلغ المتفق عليه', 'request_status' => 'حالة الطلب', 'update_status' => 'حالة التحديث', 'update_date' => 'تاريخ التحديث',
        'update_notes' => 'تحديث حالة الطلب', 'follow_up_date' => 'تاريخ المتابعة', 'visit_date' => 'تاريخ الزيارة', 'visit_time' => 'وقت الزيارة', 'visit_status' => 'حالة الزيارة',
        'lead_id' => 'المهتم', 'property_id' => 'العقار', 'assigned_employee_id' => 'الموظف المسؤول', 'properties' => 'العقارات المهتم فيها',
        'employee_id' => 'الموظف', 'employee_number' => 'الرقم الوظيفي', 'national_id' => 'رقم الهوية', 'department_id' => 'القسم', 'job_title' => 'المسمى الوظيفي',
        'contract_type_id' => 'نوع العقد', 'shift_id' => 'الوردية', 'hire_date' => 'تاريخ التعيين', 'basic_salary' => 'الراتب الأساسي', 'marital_status' => 'الحالة الاجتماعية',
        'housing_allowance' => 'بدل السكن', 'transport_allowance' => 'بدل النقل', 'phone_allowance' => 'بدل الهاتف', 'other_allowances' => 'بدلات أخرى',
        'commission_rate' => 'نسبة العمولة', 'min_sales_target' => 'هدف المبيعات', 'permission_groups' => 'مجموعات الصلاحيات',
        'date' => 'التاريخ', 'check_in' => 'الحضور', 'check_out' => 'الانصراف', 'worked_hours' => 'ساعات العمل', 'late_minutes' => 'دقائق التأخير', 'daily_update' => 'التحديث اليومي',
        'leave_type_id' => 'نوع الإجازة', 'from_date' => 'من تاريخ', 'to_date' => 'إلى تاريخ', 'days_count' => 'عدد الأيام', 'manager_status' => 'قرار المدير', 'hr_status' => 'قرار الموارد البشرية',
        'final_status' => 'الحالة النهائية', 'manager_id' => 'المدير', 'hr_employee_id' => 'موظف الموارد البشرية', 'duration' => 'المدة', 'clarification' => 'التوضيح',
        'start_time' => 'البداية', 'end_time' => 'النهاية', 'late_tolerance_minutes' => 'سماحية التأخير', 'total_days' => 'عدد الأيام', 'file_name' => 'اسم الملف',
        'audience' => 'الفئة', 'auto_status' => 'الإرسال التلقائي', 'page_key' => 'الصفحة', 'can_view' => 'عرض', 'can_add' => 'إضافة', 'can_edit' => 'تعديل',
        'can_delete' => 'حذف', 'can_export' => 'تصدير', 'can_approve' => 'موافقة', 'old_salary' => 'الراتب القديم', 'new_salary' => 'الراتب الجديد',
        'market_price_3months' => 'سعر السوق (3 أشهر)', 'market_price_note' => 'ملاحظة سعر السوق', 'map_url' => 'رابط الخريطة', 'latitude' => 'خط العرض', 'longitude' => 'خط الطول',
        'nearby_places' => 'الأماكن القريبة', 'advertiser_role' => 'صفة المعلن', 'mortgage_status' => 'حالة الرهن', 'mortgage_amount' => 'مبلغ الرهن', 'rental_status' => 'حالة الإيجار',
        'annual_income' => 'الدخل السنوي', 'annual_return_rate' => 'العائد السنوي', 'tenants_count' => 'عدد المستأجرين', 'contract_duration' => 'مدة العقد', 'contract_type_rental' => 'نوع عقد الإيجار',
        'rental_info' => 'معلومات الإيجار', 'contract_end_date' => 'نهاية العقد', 'rooms_count' => 'الغرف', 'bathrooms_count' => 'دورات المياه', 'halls_count' => 'الصالات', 'kitchens_count' => 'المطابخ',
        'floors_count' => 'الأدوار', 'units_count' => 'الوحدات', 'dimensions' => 'الأبعاد', 'building_age' => 'عمر البناء', 'is_furnished' => 'مفروش', 'has_pool' => 'مسبح', 'has_garden' => 'حديقة',
        'has_elevator' => 'مصعد', 'parking' => 'المواقف', 'ac_type' => 'نوع التكييف', 'has_internet' => 'إنترنت', 'has_security' => 'حراسة', 'heating_type' => 'نوع التدفئة', 'additional_info' => 'معلومات إضافية',
        'photos_url' => 'رابط الصور', 'video_url' => 'رابط الفيديو', 'virtual_tour_url' => 'الجولة الافتراضية', 'photos_count' => 'عدد الصور', 'photo_status' => 'التصوير', 'design_status' => 'التصميم',
        'video_status' => 'الفيديو', 'marketing_status' => 'حالة التسويق', 'publishing_status' => 'النشر', 'price_update_date' => 'تاريخ تحديث السعر', 'agreement_end_date' => 'نهاية الاتفاقية', 'group' => 'المجموعة',
        'is_available' => 'متاح', 'assigned_to_specialist_at' => 'تاريخ التحويل للأخصائي', 'visit_coordinated' => 'تنسيق الزيارة', 'visit_code' => 'كود الزيارة', 'detailed_address' => 'العنوان التفصيلي',
        'break_start' => 'بداية الاستراحة', 'break_end' => 'نهاية الاستراحة', 'overtime_hours' => 'ساعات إضافية', 'late_deduction' => 'خصم التأخير', 'early_leave_deduction' => 'خصم الخروج المبكر',
        'occupancy_allowance' => 'بدل الإشغال', 'is_approved' => 'معتمد', 'approved_by' => 'اعتمده', 'overtime_multiplier' => 'معامل الإضافي', 'deduction_after_late_days' => 'الخصم بعد أيام تأخير',
        'weekend_days' => 'أيام العطلة', 'year' => 'السنة', 'used_days' => 'الأيام المستخدمة', 'last_leave_date' => 'آخر إجازة', 'request_number' => 'رقم الطلب', 'attachment_path' => 'المرفق',
        'manager_notes' => 'ملاحظات المدير', 'manager_decision_at' => 'وقت قرار المدير', 'hr_notes' => 'ملاحظات الموارد البشرية', 'hr_decision_at' => 'وقت قرار الموارد البشرية',
        'request_datetime' => 'وقت الطلب', 'note' => 'ملاحظة', 'file_path' => 'الملف', 'ad_license_expiry' => 'انتهاء رخصة الإعلان', 'fal_license_number' => 'رخصة فال', 'listing_purpose' => 'بيع/إيجار',
        'workflow_status' => 'حالة النشر', 'return_note' => 'ملاحظة الإرجاع', 'public_base_url' => 'رابط النظام العام', 'wordpress_base_url' => 'رابط ووردبريس',
        'wordpress_username' => 'مستخدم ووردبريس', 'wordpress_app_password' => 'كلمة مرور ووردبريس', 'wordpress_mock' => 'الوضع التجريبي', 'bayut_token' => 'توكن بيوت', 'effective_date' => 'تاريخ السريان', 'min_delay' => 'أقل فاصل', 'max_delay' => 'أعلى فاصل', 'next_send_at' => 'الإرسال التالي',
    ];

    // الحقول المرجعية: منخزّن الاسم مش الرقم
    private const REFERENCES = [
        'property_type_id' => ['PropertyType', 'name'], 'marketing_type_id' => ['MarketingType', 'name'], 'usage_type_id' => ['UsageType', 'name'],
        'city_id' => ['City', 'name'], 'neighborhood_id' => ['Neighborhood', 'name'], 'owner_id' => ['Owner', 'name'], 'payment_method_id' => ['PaymentMethod', 'name'],
        'department_id' => ['Department', 'name'], 'contract_type_id' => ['ContractType', 'name'], 'shift_id' => ['Shift', 'name'], 'leave_type_id' => ['LeaveType', 'name'],
        'lead_id' => ['Lead', 'lead_code'], 'property_id' => ['Property', 'property_code'],
        'employee_id' => ['Employee', 'full_name'], 'operation_employee_id' => ['Employee', 'full_name'], 'broker_employee_id' => ['Employee', 'full_name'],
        'assigned_employee_id' => ['Employee', 'full_name'], 'sales_employee_owners_id' => ['Employee', 'full_name'], 'sales_employee_leads_id' => ['Employee', 'full_name'],
        'manager_id' => ['Employee', 'full_name'], 'hr_employee_id' => ['Employee', 'full_name'], 'approved_by' => ['Employee', 'full_name'],
    ];

    public static function handle(string $action, Model $model): void
    {
        $class = get_class($model);
        if (!str_starts_with($class, 'App\\Models\\') || in_array(class_basename($class), self::SKIPPED, true)) return;
        if (!auth()->id()) return;

        try {
            $changes = match ($action) {
                'created' => self::createdFields($model),
                'updated' => self::updatedFields($model),
                default   => null,
            };
            if ($action === 'updated' && !$changes) return;   // تغييرات داخلية بس (مثلاً الاسترجاع)

            self::write($action, $model, $changes);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // تغييرات علاقات متعدد-لمتعدد (ما إلها أحداث بالموديل): العقارات المهتم فيها، مجموعات الصلاحيات...
    public static function relationChanged(Model $model, string $field, array $old, array $new): void
    {
        if (!auth()->id() || !array_diff($old, $new) && !array_diff($new, $old)) return;

        try {
            self::write('updated', $model, [[
                'field' => $field,
                'label' => self::LABELS[$field] ?? $field,
                'old'   => implode('، ', $old) ?: null,
                'new'   => implode('، ', $new) ?: null,
            ]]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function write(string $action, Model $model, ?array $changes): void
    {
        $user = auth()->user();

        AuditLog::create([
            'employee_id'   => $user->id,
            'employee_name' => $user->full_name ?? null,
            'action'        => $action,
            'subject_type'  => class_basename($model),
            'subject_id'    => $model->getKey(),
            'subject_label' => Str::limit(self::labelOf($model), 250, '…'),
            'changes'       => $changes ?: null,
            'ip'            => request()?->ip(),
        ]);
    }

    private static function createdFields(Model $model): array
    {
        $out = [];
        foreach (array_keys($model->getAttributes()) as $field) {
            if (in_array($field, self::IGNORED_FIELDS, true)) continue;

            $new = self::display($field, $model->getAttribute($field));
            if ($new === null || $new === '') continue;
            $out[] = ['field' => $field, 'label' => self::LABELS[$field] ?? $field, 'old' => null, 'new' => $new];
        }
        return $out;
    }

    private static function updatedFields(Model $model): array
    {
        $out = [];
        foreach (array_keys($model->getChanges()) as $field) {
            if (in_array($field, self::IGNORED_FIELDS, true)) continue;

            $old = self::display($field, $model->getOriginal($field));
            $new = self::display($field, $model->getAttribute($field));
            if ($old === $new && !in_array($field, self::SECRET_FIELDS, true)) continue;
            $out[] = ['field' => $field, 'label' => self::LABELS[$field] ?? $field, 'old' => $old, 'new' => $new];
        }
        return $out;
    }

    // قيمة قابلة للقراءة: اسم بدل الرقم المرجعي، نعم/لا، بدون وسوم HTML، وبدون أسرار
    private static function display(string $field, mixed $value): ?string
    {
        if (in_array($field, self::SECRET_FIELDS, true)) return '••••••';
        if ($value === null) return null;

        if (isset(self::REFERENCES[$field])) {
            [$model, $column] = self::REFERENCES[$field];
            $name = ('App\\Models\\' . $model)::withoutGlobalScopes()->whereKey($value)->value($column);
            return $name !== null && $name !== '' ? (string) $name : '#' . $value;
        }
        if (is_bool($value))                 return $value ? 'نعم' : 'لا';
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i:s') === '00:00:00' ? $value->toDateString() : $value->toDateTimeString();
        }
        if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);

        $text = trim(strip_tags(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8')));
        if (preg_match('/^-?\d+\.\d+$/', $text)) $text = rtrim(rtrim($text, '0'), '.');

        return Str::limit($text, 300, '…');
    }

    private static function labelOf(Model $model): string
    {
        $parts = [];
        foreach (['property_code', 'owner_code', 'lead_code', 'visit_code', 'employee_number', 'request_number'] as $code) {
            if ($model->getAttribute($code)) { $parts[] = $model->getAttribute($code); break; }
        }
        foreach (['name', 'full_name', 'title', 'file_name', 'page_key'] as $name) {
            if ($model->getAttribute($name)) { $parts[] = $model->getAttribute($name); break; }
        }
        if (!$parts && $model->getAttribute('employee_id')) {
            $parts[] = self::display('employee_id', $model->getAttribute('employee_id'));
            if ($date = self::display('date', $model->getAttribute('date'))) $parts[] = $date;
        }
        return implode(' · ', $parts) ?: '#' . $model->getKey();
    }
}
