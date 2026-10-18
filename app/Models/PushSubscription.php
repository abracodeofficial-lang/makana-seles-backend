<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = ['employee_id', 'endpoint', 'p256dh', 'auth', 'user_agent'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
