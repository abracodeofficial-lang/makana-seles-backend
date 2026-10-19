<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

// إعدادات برنامج ربط الواتساب (سطر واحد)
class WaGatewaySetting extends Model
{
    protected $fillable = ['token', 'daily_limit', 'work_start', 'work_end', 'paused'];
    protected $casts    = ['paused' => 'boolean', 'daily_limit' => 'integer'];
    protected $hidden   = ['token'];

    public static function current(): self
    {
        return static::firstOrCreate([], ['token' => Str::random(48)]);
    }
}
