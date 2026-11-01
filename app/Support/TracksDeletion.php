<?php

namespace App\Support;

use Illuminate\Database\Eloquent\SoftDeletes;

// حذف ناعم + مين اللي حذف — السجلات المحذوفة بتنعرض بصفحة المحذوفات (TrashController)
trait TracksDeletion
{
    use SoftDeletes;

    public static function bootTracksDeletion(): void
    {
        static::deleted(function ($model) {
            if ($model->isForceDeleting() || !auth()->id()) return;
            $model->newQueryWithoutScopes()->whereKey($model->getKey())->update(['deleted_by' => auth()->id()]);
        });

        static::restoring(function ($model) {
            $model->deleted_by = null;
        });
    }
}
