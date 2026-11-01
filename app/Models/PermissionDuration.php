<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionDuration extends Model
{
    use \App\Support\TracksDeletion;

    protected $fillable = ['name', 'sort_order'];
}
