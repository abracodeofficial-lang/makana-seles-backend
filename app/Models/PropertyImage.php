<?php

namespace App\Models;

use App\Models\PublishingSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PropertyImage extends Model
{
    use \App\Support\TracksDeletion;

    protected $fillable = ['property_id', 'uuid', 'file_path', 'file_name', 'sort_order'];
    protected $appends  = ['url'];

    public function property(): BelongsTo { return $this->belongsTo(Property::class); }

    // رابط عام بدون تسجيل دخول (ووردبريس وبيوت بينزلوا الصور منه) — بالـ uuid حتى ما ينعدّ بالأرقام
    public function getUrlAttribute(): string
    {
        return PublishingSetting::current()->publicUrl("media/property-images/{$this->uuid}");
    }

    protected static function booted(): void
    {
        static::creating(function (PropertyImage $image) {
            if (empty($image->uuid)) $image->uuid = (string) Str::uuid();
        });

        // تغيير الصور بيغيّر بيانات الإعلان
        foreach (['created', 'deleted', 'restored'] as $event) {
            static::$event(function (PropertyImage $image) {
                if ($image->property) app(\App\Services\Publishing\PublicationService::class)->propertyChanged($image->property);
            });
        }
    }
}
