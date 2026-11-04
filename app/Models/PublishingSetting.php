<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PublishingSetting extends Model
{
    protected $fillable = ['public_base_url', 'wordpress_base_url', 'wordpress_username', 'wordpress_app_password', 'wordpress_mock', 'bayut_token'];

    protected $casts = [
        'wordpress_app_password' => 'encrypted',
        'bayut_token'            => 'encrypted',
        'wordpress_mock'         => 'boolean',
    ];

    protected $hidden = ['wordpress_app_password', 'bayut_token'];

    public static function current(): self
    {
        return static::first() ?? static::create([
            'public_base_url' => env('PUBLIC_BASE_URL', 'https://system.makanasa.com'),
            'wordpress_mock'  => true,
            'bayut_token'     => Str::random(48),
        ]);
    }

    public function publicUrl(string $path): string
    {
        return rtrim($this->public_base_url ?: config('app.url'), '/') . '/' . ltrim($path, '/');
    }

    public function bayutFeedUrl(): string
    {
        return $this->publicUrl('feeds/bayut.xml?token=' . $this->bayut_token);
    }
}
