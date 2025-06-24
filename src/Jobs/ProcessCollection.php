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
        public readonly string $collection,
        public readonly ?string $since,
    ) {}

    public function handle(): void
    {
        $totalChunks = 0;

        $collectionName = ConfigResolver::getCollectionName($this->collection);

        $config = ConfigResolver::getConfig($this->collection);

        $builder = new QueryBuilder($config, $this->since);

        $builder->getQuery()->chunk(
            count: 90,
            callback: function (EloquentCollection $items) use (&$totalChunks) {
                $totalChunks++;

                dispatch(new UpsertItems(
                    collection: $this->collection,
                    items: $items,
                ))->onQueue($this->queue);

                // Free memory
                unset($items);
            }
        );

        Log::info("[Vectorify] Successfully processed chunks for collection: {$collectionName}", [
            'package' => 'vectorify',
            'total_chunks' => $totalChunks,
        ]);

        if ($totalChunks > 0) {
            Cache::put(
                "vectorify:last_upsert:{$collectionName}",
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
        $collectionName = ConfigResolver::getCollectionName($this->collection);

        Log::error("[Vectorify] Collection processing permanently failed for {$collectionName}", [
            'package' => 'vectorify',
            'error' => $exception->getMessage(),
        ]);
    }
}
