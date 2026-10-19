<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaCampaignRecipient extends Model
{
    protected $fillable = ['wa_campaign_id', 'contact_id', 'name', 'phone', 'status', 'sent_at', 'sent_by', 'error', 'via'];
    protected $casts    = ['sent_at' => 'datetime'];
}
