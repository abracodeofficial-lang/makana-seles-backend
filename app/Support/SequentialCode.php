<?php

namespace App\Support;

use Illuminate\Database\Eloquent\SoftDeletes;

// توليد الأكواد المتسلسلة (RE-2026-001، INT-2026-001، LR-001...).
// كان الكود ينحسب من "عدد السجلات + 1" — بس السجلات المحذوفة (soft delete) ما بتنعد
// وكودها لسا محجوز، فبيطلع كود مكرر وبيرفضه الـunique → خطأ 500 عند الإضافة.
// هلق: أعلى رقم موجود (مع المحذوف) + 1، ومنتأكد إنه مش مستخدم.
class SequentialCode
{
    public static function next(string $modelClass, string $column, string $prefix, int $pad = 3): string
    {
        $query = $modelClass::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        $max = $query->where($column, 'like', $prefix . '%')
            ->pluck($column)
            ->map(fn ($code) => (int) preg_replace('/\D/', '', substr($code, strlen($prefix))))
            ->max() ?? 0;

        $n = $max + 1;
        do {
            $code = $prefix . str_pad((string) $n++, $pad, '0', STR_PAD_LEFT);
            $taken = $modelClass::query()
                ->when(in_array(SoftDeletes::class, class_uses_recursive($modelClass), true), fn ($q) => $q->withTrashed())
                ->where($column, $code)->exists();
        } while ($taken);

        return $code;
    }
}
