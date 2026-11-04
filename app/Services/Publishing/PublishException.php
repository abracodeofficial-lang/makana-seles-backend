<?php

namespace App\Services\Publishing;

use RuntimeException;

// retryable = خطأ مؤقت (شبكة/5xx) بيعيد المحاولة، غيره بيفشل فوراً
class PublishException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
