<?php

namespace App\Services\Publishing;

use App\Models\PropertyPublication;

class PublishResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $externalId = null,
        public readonly ?string $externalUrl = null,
        public readonly ?string $request = null,
        public readonly ?string $response = null,
    ) {}

    public static function published(?string $id = null, ?string $url = null, ?string $request = null, ?string $response = null): self
    {
        return new self(PropertyPublication::PUBLISHED, $id, $url, $request, $response);
    }

    // المنصة اليدوية: بينتظر الناشر ينشر بنفسه ويأكّد بالرابط
    public static function awaitingManual(?string $request = null): self
    {
        return new self(PropertyPublication::PENDING, null, null, $request, 'بانتظار النشر اليدوي');
    }

    public static function unpublished(?string $request = null, ?string $response = null): self
    {
        return new self(PropertyPublication::UNPUBLISHED, null, null, $request, $response);
    }
}
