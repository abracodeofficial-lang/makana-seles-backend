<?php

namespace App\Http\Controllers;

use App\Models\PropertyImage;
use App\Models\PublishingSetting;
use App\Services\Publishing\BayutFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

// مسارات عامة بدون تسجيل دخول: Feed بيوت (بتوكن) وصور الإعلانات
class FeedController extends Controller
{
    // GET /feeds/bayut.xml?token=
    public function bayut(Request $request)
    {
        $expected = (string) PublishingSetting::current()->bayut_token;
        abort_unless($expected !== '' && hash_equals($expected, (string) $request->query('token')), 403);

        return response(BayutFeed::xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    // GET /media/property-images/{uuid}
    public function image(string $uuid)
    {
        $image = PropertyImage::where('uuid', $uuid)->firstOrFail();
        abort_unless(Storage::disk('public')->exists($image->file_path), 404);

        return response()->file(Storage::disk('public')->path($image->file_path), ['Cache-Control' => 'public, max-age=86400']);
    }
}
