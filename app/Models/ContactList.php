<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// قائمة أرقام محفوظة (ملاك أو مهتمين) لإعادة استخدامها بالرسائل الجماعية
class ContactList extends Model
{
    protected $fillable = ['name', 'audience', 'created_by'];

    public function members(): HasMany     { return $this->hasMany(ContactListMember::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(Employee::class, 'created_by'); }
}
