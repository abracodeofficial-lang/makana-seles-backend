<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    // دائماً أرجع 401 JSON للـ API — لا تحاول redirect لـ route('login')
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        return response()->json([
            'status'  => false,
            'message' => 'غير مصرح. يرجى تسجيل الدخول مجدداً.',
        ], 401);
    }
}
