<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['publication_id', 'action', 'status', 'request_summary', 'response_summary', 'user_id'];

    public function publication(): BelongsTo { return $this->belongsTo(PropertyPublication::class, 'publication_id'); }
    public function user(): BelongsTo        { return $this->belongsTo(Employee::class, 'user_id'); }
}
