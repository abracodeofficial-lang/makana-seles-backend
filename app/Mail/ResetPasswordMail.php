<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Employee $employee, public string $token)
    {
    }

    public function build(): self
    {
        $base = rtrim(config('app.frontend_url', 'https://system.makanasa.com'), '/');

        return $this->subject('استعادة كلمة المرور — نظام الإدارة')
            ->view('emails.reset-password')
            ->with([
                'fullName' => $this->employee->full_name,
                'resetUrl' => $base . '/reset-password?' . http_build_query(['token' => $this->token, 'email' => $this->employee->email]),
                'minutes'  => config('auth.passwords.employees.expire'),
            ]);
    }
}
