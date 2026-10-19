<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// حملة رسائل واتساب جماعية
class WaCampaign extends Model
{
    protected $fillable = ['title', 'audience', 'message', 'min_delay', 'max_delay', 'created_by'];

    public function recipients(): HasMany  { return $this->hasMany(WaCampaignRecipient::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(Employee::class, 'created_by'); }
}
