<?php

namespace Vectorify\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vectorify\Laravel\Support\ConfigResolver;
use Vectorify\Laravel\Support\QueryBuilder;
use Vectorify\Objects\CollectionObject;
use Vectorify\Objects\ItemObject;
use Vectorify\Objects\UpsertObject;
use Vectorify\Vectorify;

final class UpsertItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120; // 2 minutes per chunk

    public function __construct(
        public readonly string $collectionId,
        public readonly EloquentCollection $items,
    ) {}

    public function handle(): void
    {
        $collectionSlug = ConfigResolver::getCollectionSlug($this->collectionId);

        $config = ConfigResolver::getConfig($this->collectionId);

        Log::info("[Vectorify] Upserting items for collection: {$collectionSlug}", [
            'package' => 'vectorify',
            'chunk_size' => $this->items->count(),
        ]);

        try {
            $builder = new QueryBuilder($config, null);

            $items = $this->items->map(function (Model $item) use ($builder) {
                $object = new ItemObject(
                    id: $item->getKey(),
                    data: $builder->getItemData($item),
                    metadata: $builder->getItemMetadata(),
                    tenant: $builder->getItemTenant(),
                    url: null,
                );

                $builder->resetItemData();

                return $object;
            });

            $object = new UpsertObject(
                collection: new CollectionObject(
                    slug: $collectionSlug,
                    metadata: $builder->metadata,
                ),
                items: $items->toArray(),
            );

            $vectorify = new Vectorify(
                apiKey: (string) config('vectorify.api_key'),
                timeout: (int) config('vectorify.timeout'),
                cache: Cache::store(),
            );

            $response = $vectorify->upserts->create($object);

            if (! $response) {
                throw new \RuntimeException("Failed to upsert chunk for collection: {$collectionSlug}");
            }

            Log::info("[Vectorify] Successfully upserted items for collection: {$collectionSlug}", [
                'package' => 'vectorify',
                'chunk_size' => $this->items->count(),
            ]);
        } catch (Throwable $e) {
            Log::error("[Vectorify] Upserting failed for collection: {$collectionSlug}", [
                'package' => 'vectorify',
                'chunk_size' => $this->items->count(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function failed(Throwable $exception): void
    {
        $collectionSlug = ConfigResolver::getCollectionSlug($this->collectionId);

        Log::error("[Vectorify] Upserting permanently failed for collection: {$collectionSlug}", [
            'package' => 'vectorify',
            'chunk_size' => $this->items->count(),
            'error' => $exception->getMessage(),
        ]);
    }
}
