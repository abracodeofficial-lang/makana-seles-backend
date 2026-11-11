<?php

namespace App\Services\Publishing\Platforms;

use App\Models\Property;
use App\Models\PropertyPublication;
use App\Models\PublishingSetting;
use App\Services\Publishing\BasePublisher;
use App\Services\Publishing\ListingPayload;
use App\Services\Publishing\PublishException;
use App\Services\Publishing\PublishResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// موقع ووردبريس (إضافة makana/v1) — بوضع Mock لحد ما تجهز الإضافة
class WordPressPublisher extends BasePublisher
{
    private const MOCK_PREFIX = 'mock-';

    public function key(): string { return 'wordpress'; }
    protected function label(): string { return 'موقع ووردبريس'; }

    public function describe(): array
    {
        return array_merge(parent::describe(), [
            'notice' => $this->settings()->wordpress_mock ? 'وضع تجريبي: ما بينشر فعلياً على الموقع' : null,
        ]);
    }

    public function validate(Property $property): array
    {
        $missing = parent::validate($property);
        $s = $this->settings();
        if (!$s->wordpress_mock && (!$s->wordpress_base_url || !$s->wordpress_username || !$s->wordpress_app_password)) {
            $missing[] = 'إعدادات ربط ووردبريس';
        }
        return $missing;
    }

    public function publish(Property $property, PropertyPublication $publication): PublishResult
    {
        return $this->store('POST', 'listings', $property);
    }

    public function update(Property $property, PropertyPublication $publication): PublishResult
    {
        // إعلان Mock أو بدون رقم: لسا ما انشر فعلياً على الموقع
        if (!$publication->external_id || str_starts_with($publication->external_id, self::MOCK_PREFIX)) {
            return $this->publish($property, $publication);
        }
        return $this->store('PUT', "listings/{$publication->external_id}", $property);
    }

    public function unpublish(Property $property, PropertyPublication $publication): PublishResult
    {
        $id = $publication->external_id;
        if ($this->settings()->wordpress_mock || !$id || str_starts_with($id, self::MOCK_PREFIX)) {
            return PublishResult::unpublished("DELETE listings/{$id} (mock)", 'تم الإيقاف');
        }

        $response = $this->send('DELETE', "listings/{$id}");
        return PublishResult::unpublished("DELETE listings/{$id}", 'HTTP ' . $response->status());
    }

    private function store(string $method, string $path, Property $property): PublishResult
    {
        if ($this->settings()->wordpress_mock) {
            $id = self::MOCK_PREFIX . $property->id;
            return PublishResult::published($id, "https://mock.invalid/listing/{$property->uuid}", "$method $path (mock)", 'وضع تجريبي');
        }

        $response = $this->send($method, $path, ListingPayload::build($property), $property->uuid);
        $id  = $response->json('id');
        $url = $response->json('url');
        if (!$id || !$url) throw new PublishException('رد ووردبريس بدون id أو url');

        return PublishResult::published((string) $id, (string) $url, "$method $path", 'HTTP ' . $response->status());
    }

    private function send(string $method, string $path, array $payload = [], ?string $idempotencyKey = null)
    {
        $s = $this->settings();
        if (!str_starts_with(strtolower((string) $s->wordpress_base_url), 'https://')) {
            throw new PublishException('رابط ووردبريس لازم يكون HTTPS');
        }

        $url = rtrim($s->wordpress_base_url, '/') . '/wp-json/makana/v1/' . $path;

        try {
            $request = Http::withBasicAuth((string) $s->wordpress_username, (string) $s->wordpress_app_password)
                ->acceptJson()->timeout(120); // الإضافة بتنزّل الصور أول مرة
            if ($idempotencyKey) $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);

            $response = $request->send($method, $url, $payload ? ['json' => $payload] : []);
        } catch (ConnectionException $e) {
            throw new PublishException('تعذّر الاتصال بووردبريس', true);
        }

        if ($response->successful() || ($method === 'DELETE' && $response->status() === 404)) return $response;

        $detail = Str::limit((string) ($response->json('message') ?? $response->body()), 300);
        $temporary = $response->serverError() || in_array($response->status(), [408, 429], true);
        throw new PublishException("ووردبريس رد بخطأ {$response->status()}: {$detail}", $temporary);
    }

    // فحص الاتصال بالإضافة (زر "اختبار الاتصال" بإعدادات الربط)
    public function ping(): array
    {
        $response = $this->send('GET', 'ping');
        return (array) $response->json();
    }

    private function settings(): PublishingSetting
    {
        return PublishingSetting::current();
    }
}
