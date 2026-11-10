<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

// بيفحص الاتصال بسيرفر البريد (بدون ما يبعت إيميل) وبيحفظ النتيجة للتشخيص بدون دخول للسيرفر
class MailCheck extends Command
{
    protected $signature   = 'mail:check';
    protected $description = 'Check the SMTP connection and store the result';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $conf   = config("mail.mailers.$mailer", []);

        try {
            $transport = Mail::mailer()->getSymfonyTransport();
            if ($transport instanceof EsmtpTransport) {
                $transport->start();
                $transport->stop();
            }
            $result = 'ok';
        } catch (\Throwable $e) {
            // بدون إيميلات أو كلمات مرور بالنتيجة
            $message = preg_replace('/\S+@\S+/', '***', $e->getMessage());
            $result  = 'failed: ' . class_basename($e) . ' ' . Str::limit(trim($message), 160);
        }

        $line = sprintf('%s | mailer=%s host=%s port=%s | %s',
            now()->toIso8601String(), $mailer, $conf['host'] ?? '-', $conf['port'] ?? '-', $result);

        Storage::disk('local')->put('webpush/mail-status.txt', $line);
        $this->line($result === 'ok' ? 'mail check: ok' : 'mail check: failed');

        return self::SUCCESS;
    }
}
