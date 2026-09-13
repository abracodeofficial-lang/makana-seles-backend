<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmployeeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Employee $employee)
    {
    }

    public function build(): self
    {
        return $this->subject('تم إضافتك كموظف في نظام الإدارة')
            ->view('emails.welcome-employee')
            ->with([
                'fullName' => $this->employee->full_name,
                'email'    => $this->employee->email,
                'loginUrl' => config('app.frontend_url', 'https://system.makanasa.com'),
            ]);
    }
}
