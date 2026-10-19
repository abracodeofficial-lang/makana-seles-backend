<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactListMember extends Model
{
    protected $fillable = ['contact_list_id', 'contact_id'];
}
