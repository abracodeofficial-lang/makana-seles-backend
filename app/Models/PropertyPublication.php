<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyPublication extends Model
{
    public const PENDING = 'pending', QUEUED = 'queued', PUBLISHING = 'publishing', PUBLISHED = 'published',
                 FAILED = 'failed', NEEDS_UPDATE = 'needs_update', UNPUBLISHED = 'unpublished';

    protected $fillable = [
        'property_id', 'platform', 'status', 'external_id', 'external_url', 'payload_hash',
        'error_message', 'published_by', 'published_at', 'last_synced_at',
    ];

    protected $casts = ['published_at' => 'datetime', 'last_synced_at' => 'datetime'];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }
    public function logs(): HasMany       { return $this->hasMany(PublicationLog::class, 'publication_id'); }

    public function log(string $action, string $status, ?string $request = null, ?string $response = null, ?int $userId = null): void
    {
        $this->logs()->create([
            'action' => $action, 'status' => $status, 'user_id' => $userId,
            'request_summary' => $request, 'response_summary' => $response,
        ]);
    }
}
