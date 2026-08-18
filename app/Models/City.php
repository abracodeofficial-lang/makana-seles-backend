<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = ['name', 'code'];

    public function neighborhoods(): HasMany
    {
        return $this->hasMany(Neighborhood::class);
    }

    public function owners(): HasMany { return $this->hasMany(Owner::class); }
    public function properties(): HasMany { return $this->hasMany(Property::class); }
    public function leads(): HasMany { return $this->hasMany(Lead::class); }
}
