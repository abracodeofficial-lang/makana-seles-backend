<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// مسارات عامة لموديول النشر (Feed بيوت بتوكن، وصور الإعلانات)
Route::get('feeds/bayut.xml', [\App\Http\Controllers\FeedController::class, 'bayut'])->middleware('throttle:60,1');
Route::get('media/property-images/{uuid}', [\App\Http\Controllers\FeedController::class, 'image'])->whereUuid('uuid');

Route::get('/', function () {
    return view('welcome');
});
