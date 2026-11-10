<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// رسالة واتساب فورية بيبعتها برنامج المكتب (tools/wa-gateway) قبل رسائل الحملات
class WaOutbox extends Model
{
    protected $table    = 'wa_outbox';
    protected $fillable = ['phone', 'message', 'purpose', 'status', 'error', 'expires_at', 'sent_at'];
    protected $casts    = ['expires_at' => 'datetime', 'sent_at' => 'datetime'];
}
