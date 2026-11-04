<?php

namespace App\Providers;

use App\Support\AuditTrail;
use App\Support\ColumnDefaults;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // حقل فاضي بأي فورم ما لازم يعمل خطأ 500 — قبل حفظ أي موديل منعبّي القيم الافتراضية
        // للأعمدة اللي ما بتقبل NULL (شوف App\Support\ColumnDefaults)
        Event::listen('eloquent.saving: *', function (string $event, array $models) {
            foreach ($models as $model) {
                ColumnDefaults::apply($model);
            }
        });

        // موديول النشر: الناشر بينشر وما بيعدّل، مدخل البيانات بيعدّل وما بيوصل للنشر
        Gate::define('publishing.view',  fn($u) => $u->isAdmin() || $u->hasPermission('publishing', 'view'));
        Gate::define('publishing.act',   fn($u) => $u->isAdmin() || $u->hasPermission('publishing', 'edit'));
        Gate::define('publishing.admin', fn($u) => $u->isAdmin());
        Gate::define('property.write',   fn($u) => $u->canWriteProperties());

        // سجل العمليات: كل إضافة/تعديل/حذف/استرجاع (شوف App\Support\AuditTrail)
        foreach (['created', 'updated', 'deleted', 'restored'] as $action) {
            Event::listen("eloquent.$action: *", function (string $event, array $models) use ($action) {
                foreach ($models as $model) {
                    AuditTrail::handle($action, $model);
                }
            });
        }
    }
}
