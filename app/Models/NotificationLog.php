<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;




// ============================================================
// NOTIFICATION LOG
// ============================================================
class NotificationLog extends Model
{
    protected $table    = 'notifications_log';
    protected $fillable = ['employee_id', 'title', 'body', 'type', 'is_read', 'url'];
    protected $casts    = ['is_read' => 'boolean'];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }

    public function scopeUnread($q) { return $q->where('is_read', false); }

    // إنشاء إشعار سريع
    public static function send(int $employeeId, string $title, string $body, string $type = 'معلومة', ?string $url = null): self
    {
        return static::create([
            'employee_id' => $employeeId,
            'title'       => $title,
            'body'        => $body,
            'type'        => $type,
            'url'         => $url,
        ]);
    }
}