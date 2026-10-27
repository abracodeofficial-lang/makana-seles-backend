<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// قبل حفظ أي موديل: أي حقل قيمته null وعموده بالـDB ما بيقبل NULL → منحط القيمة الافتراضية للعمود.
// السبب: Laravel بيحوّل الحقل الفاضي بالفورم ("") لـ null، والأعمدة NOT NULL بترفضه → خطأ 500.
// (حادثة: نسبة الجدية بالمهتمين، عرض الشارع بالعقارات...). بينطبق على كل الجداول من مكان واحد.
class ColumnDefaults
{
    /** @var array<string, array<string, array{nullable: bool, default: mixed, type: string, column_type: string}>> */
    private static array $schema = [];

    public static function apply(Model $model): void
    {
        $connection = $model->getConnection();
        if ($connection->getDriverName() !== 'mysql') return;

        $columns = self::columns($model);
        if (!$columns) return;

        foreach ($model->getAttributes() as $key => $value) {
            if ($value !== null || !isset($columns[$key])) continue;
            $col = $columns[$key];
            if ($col['nullable']) continue;

            $fallback = self::fallback($key, $col);
            if ($fallback !== null) {
                $model->setAttribute($key, $fallback);
            }
        }
    }

    // القيمة البديلة: default العمود، وإذا ما إله default منختار قيمة "فاضية" مناسبة لنوعه
    private static function fallback(string $key, array $col)
    {
        if ($col['default'] !== null) return $col['default'];

        // مفاتيح أجنبية والـid: ما منخترع قيمة (الفاليديشن بيتعامل معها)
        if ($key === 'id' || str_ends_with($key, '_id')) return null;

        return match (true) {
            in_array($col['type'], ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'], true) => '',
            in_array($col['type'], ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'double', 'float'], true) => 0,
            $col['type'] === 'enum' => self::firstEnumValue($col['column_type']),
            default => null,
        };
    }

    private static function firstEnumValue(string $columnType): ?string
    {
        return preg_match("/^enum\\('((?:[^'\\\\]|\\\\.|'')*)'/i", $columnType, $m) ? str_replace("''", "'", $m[1]) : null;
    }

    private static function columns(Model $model): array
    {
        $table = $model->getTable();
        if (isset(self::$schema[$table])) return self::$schema[$table];

        $rows = DB::connection($model->getConnectionName())->select(
            'SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE, COLUMN_TYPE
               FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        $out = [];
        foreach ($rows as $r) {
            $out[$r->COLUMN_NAME] = [
                'nullable'    => $r->IS_NULLABLE === 'YES',
                'default'     => self::normalizeDefault($r->COLUMN_DEFAULT),
                'type'        => strtolower($r->DATA_TYPE),
                'column_type' => $r->COLUMN_TYPE,
            ];
        }
        return self::$schema[$table] = $out;
    }

    // MySQL بيرجّع الـdefault بدون علامات تنصيص، وMariaDB بيرجّعه 'مفتوح' أو NULL كنص
    private static function normalizeDefault($default)
    {
        if ($default === null) return null;
        $d = (string) $default;
        if (strcasecmp($d, 'NULL') === 0) return null;
        if (stripos($d, 'current_timestamp') !== false || str_contains($d, '(')) return null;   // تعابير، مش قيم
        if (strlen($d) >= 2 && $d[0] === "'" && substr($d, -1) === "'") {
            $d = str_replace("''", "'", substr($d, 1, -1));
        }
        return $d;
    }
}
