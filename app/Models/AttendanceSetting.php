<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class AttendanceSetting extends Model
{
    protected $fillable = [
        'late_tolerance_minutes',
        'overtime_multiplier',
        'deduction_after_late_days',
    ];

    // دائماً نجيب السجل الوحيد
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'late_tolerance_minutes'   => 15,
            'overtime_multiplier'      => 1.5,
            'deduction_after_late_days'=> 3,
        ]);
    }
}

