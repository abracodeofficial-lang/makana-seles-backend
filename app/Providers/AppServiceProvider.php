<?php

namespace App\Providers;

use App\Support\ColumnDefaults;
use Illuminate\Support\Facades\Event;
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
    }
}
