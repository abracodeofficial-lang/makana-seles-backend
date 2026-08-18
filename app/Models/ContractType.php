<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;



// ============================================================
class ContractType extends Model
{
    protected $fillable = ['name', 'details'];

    public function employees(): HasMany { return $this->hasMany(Employee::class); }
}

