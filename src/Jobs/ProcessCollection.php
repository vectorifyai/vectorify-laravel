<?php

namespace Vectorify\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vectorify\Laravel\Jobs\UpsertItems;
use Vectorify\Laravel\Support\ConfigResolver;
use Vectorify\Laravel\Support\QueryBuilder;

final class ProcessCollection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $collectionId,
        public readonly ?string $since,
    ) {}

    public function handle(): void
    {
        $totalChunks = 0;

        $collectionSlug = ConfigResolver::getCollectionSlug($this->collectionId);

        $config = ConfigResolver::getConfig($this->collectionId);

        $builder = new QueryBuilder($config, $this->since);

        $builder->getQuery()->chunk(
            count: 90,
            callback: function (EloquentCollection $items) use (&$totalChunks) {
                $totalChunks++;

                dispatch(new UpsertItems(
                    collectionId: $this->collectionId,
                    items: $items,
                ))->onQueue($this->queue);

                // Free memory
                unset($items);
            }
        );

        Log::info("[Vectorify] Successfully processed chunks for collection: {$collectionSlug}", [
            'package' => 'vectorify',
            'total_chunks' => $totalChunks,
        ]);

        if ($totalChunks > 0) {
            Cache::put(
                "vectorify:last_upsert:{$collectionSlug}",
                now()->toDateTimeString(),
                now()->addDays(30)
            );
        }
    }

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function failed(Throwable $exception): void
    {
        $collectionSlug = ConfigResolver::getCollectionSlug($this->collectionId);

        Log::error("[Vectorify] Collection processing permanently failed for {$collectionSlug}", [
            'package' => 'vectorify',
            'error' => $exception->getMessage(),
        ]);
    }
}
