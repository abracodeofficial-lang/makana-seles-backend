<?php

namespace App\Services\Publishing;

use App\Jobs\RunPublication;
use App\Models\Employee;
use App\Models\NotificationLog;
use App\Models\Property;
use App\Models\PropertyPublication;
use Illuminate\Support\Collection;

class PublicationService
{
    private ?array $platforms = null;

    /** @return array<string, PlatformPublisher> كل كلاس بمجلد Platforms = منصة */
    public function platforms(): array
    {
        if ($this->platforms !== null) return $this->platforms;

        $this->platforms = [];
        foreach (glob(app_path('Services/Publishing/Platforms/*.php')) as $file) {
            $class = 'App\\Services\\Publishing\\Platforms\\' . basename($file, '.php');
            if (is_subclass_of($class, PlatformPublisher::class)) {
                $platform = app($class);
                $this->platforms[$platform->key()] = $platform;
            }
        }
        return $this->platforms;
    }

    public function platform(string $key): ?PlatformPublisher
    {
        return $this->platforms()[$key] ?? null;
    }

    /**
     * يرسل المنصات المختارة للطابور — منصة فيها نواقص بتترفض لحالها وما بتوقف الباقي.
     * @return array<string, array{ok:bool, message:string}>
     */
    public function dispatch(Property $property, array $keys, int $userId): array
    {
        $results = [];

        foreach ($keys as $key) {
            $platform = $this->platform($key);
            if (!$platform) {
                $results[$key] = ['ok' => false, 'message' => 'منصة غير معروفة'];
                continue;
            }

            $missing = $platform->validate($property);
            if ($missing) {
                $results[$key] = ['ok' => false, 'message' => 'نواقص: ' . implode('، ', $missing)];
                continue;
            }

            $publication = PropertyPublication::firstOrCreate(['property_id' => $property->id, 'platform' => $key]);
            if (in_array($publication->status, [PropertyPublication::QUEUED, PropertyPublication::PUBLISHING], true)) {
                $results[$key] = ['ok' => false, 'message' => 'قيد التنفيذ'];
                continue;
            }

            $action = $publication->external_id || $publication->status === PropertyPublication::NEEDS_UPDATE ? 'update' : 'publish';
            $this->enqueue($publication, $action, $userId);
            $results[$key] = ['ok' => true, 'message' => 'انضاف للطابور'];
        }

        if (collect($results)->contains('ok', true)) {
            Property::whereKey($property->id)->update(['workflow_status' => 'in_publishing', 'return_note' => null]);
        }

        return $results;
    }

    public function enqueue(PropertyPublication $publication, string $action, int $userId): void
    {
        $publication->update(['status' => PropertyPublication::QUEUED, 'error_message' => null, 'published_by' => $userId]);
        $publication->log($action, PropertyPublication::QUEUED, 'طلب من الناشر', null, $userId);
        RunPublication::dispatch($publication->id, $action, $userId)->onConnection('database');
    }

    // تعديل عقار منشور → منشوراته بتحتاج تحديث (بس إذا البيانات المنشورة تغيّرت فعلاً)
    public function propertyChanged(Property $property): void
    {
        $published = PropertyPublication::where('property_id', $property->id)
            ->where('status', PropertyPublication::PUBLISHED)->get();
        if ($published->isEmpty()) return;

        $hash    = ListingPayload::hash($property->fresh());
        $changed = $published->filter(fn($p) => $p->payload_hash !== $hash);
        if ($changed->isEmpty()) return;

        foreach ($changed as $publication) {
            $publication->update(['status' => PropertyPublication::NEEDS_UPDATE]);
            $publication->log('update', PropertyPublication::NEEDS_UPDATE, 'تعديل على بيانات العقار', null, auth()->id());
        }

        BayutFeed::forget();
        $this->notifyPublishers('عقار يحتاج إعادة نشر', "تعدّلت بيانات {$property->name} ({$property->property_code}) وهو منشور", 'تحذير', "/publishing/{$property->id}");
    }

    public function notifyPublishers(string $title, string $body, string $type = 'معلومة', ?string $url = null): void
    {
        $this->publishers()->each(fn($e) => NotificationLog::send($e->id, $title, $body, $type, $url));
    }

    public function publishers(): Collection
    {
        return Employee::active()->get()->filter(fn($e) => $e->hasPermission('publishing', 'view') || $e->isAdmin())->values();
    }
}
