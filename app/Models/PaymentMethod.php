<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;



// ============================================================
class PaymentMethod extends Model
{
    protected $fillable = ['name', 'code'];

    public function leads(): HasMany { return $this->hasMany(Lead::class); }
}
