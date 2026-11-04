<?php

namespace App\Jobs;

use App\Models\PropertyPublication;
use App\Services\Publishing\ListingPayload;
use App\Services\Publishing\PublicationService;
use App\Services\Publishing\PublishException;
use App\Services\Publishing\PublishResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

// عملية نشر واحدة على منصة واحدة — فشلها ما بيأثر على باقي المنصات
class RunPublication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int $publicationId,
        public readonly string $action,      // publish | update | unpublish
        public readonly int $userId,
    ) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(PublicationService $service): void
    {
        $publication = PropertyPublication::with('property')->find($this->publicationId);
        if (!$publication) return;

        $property = $publication->property;
        $platform = $service->platform($publication->platform);
        if (!$property || !$platform) {
            $this->markFailed($publication, 'العقار أو المنصة غير موجودين');
            return;
        }

        // التحقق مرة تانية وقت التنفيذ (الرخصة ممكن تكون انتهت وهو بالطابور). الإيقاف مسموح دايماً.
        if ($this->action !== 'unpublish') {
            $missing = $platform->validate($property);
            if ($missing) {
                $this->markFailed($publication, 'نواقص: ' . implode('، ', $missing));
                return;
            }
        }

        $publication->update(['status' => PropertyPublication::PUBLISHING]);

        try {
            /** @var PublishResult $result */
            $result = $platform->{$this->action}($property, $publication);
        } catch (PublishException $e) {
            if ($e->retryable && $this->attempts() < $this->tries) {
                $publication->update(['status' => PropertyPublication::QUEUED, 'error_message' => $e->getMessage()]);
                $publication->log($this->action, 'retry', "محاولة {$this->attempts()}", $e->getMessage(), $this->userId);
                $this->release($this->backoff()[$this->attempts() - 1] ?? 120);
                return;
            }
            $this->markFailed($publication, $e->getMessage());
            return;
        }

        $publication->update(array_filter([
            'status'         => $result->status,
            'external_id'    => $result->externalId,
            'external_url'   => $result->externalUrl,
            'payload_hash'   => $result->status === PropertyPublication::PUBLISHED ? ListingPayload::hash($property->fresh()) : null,
            'published_at'   => $result->status === PropertyPublication::PUBLISHED ? now() : null,
        ]) + ['error_message' => null, 'last_synced_at' => now()]);

        // بعد الإيقاف إعادة النشر بتكون إعلان جديد
        if ($result->status === PropertyPublication::UNPUBLISHED) {
            $publication->update(['external_id' => null, 'external_url' => null, 'payload_hash' => null]);
        }

        $publication->log($this->action, $result->status, $result->request, $result->response, $this->userId);
    }

    public function failed(Throwable $e): void
    {
        $publication = PropertyPublication::find($this->publicationId);
        if ($publication) $this->markFailed($publication, 'خطأ غير متوقع بالنشر');
        report($e);
    }

    private function markFailed(PropertyPublication $publication, string $message): void
    {
        $publication->update(['status' => PropertyPublication::FAILED, 'error_message' => $message]);
        $publication->log($this->action, PropertyPublication::FAILED, null, $message, $this->userId);
    }
}
