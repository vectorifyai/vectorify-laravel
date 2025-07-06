<?php

namespace Vectorify\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Vectorify\Laravel\Jobs\ProcessCollection;
use Vectorify\Laravel\Support\ConfigResolver;
use Vectorify\Laravel\Support\QueryBuilder;
use Vectorify\Objects\CollectionObject;
use Vectorify\Objects\ItemObject;
use Vectorify\Objects\UpsertObject;
use Vectorify\Vectorify;

final class VectorifyUpsert extends Command
{
    protected $signature = 'vectorify:upsert
                           {--collection= : Upsert only specific collection}
                           {--force : Force full upsert, ignoring incremental updates}
                           {--since= : Upsert records updated since this date (Y-m-d H:i:s)}
                           {--queue= : Queue to use for processing jobs}';

    protected $description = 'Upsert collections to Vectorify';

    public function handle(): int
    {
        $this->info('🚀 Starting the upsert process for collections...');

        if (! config('vectorify.api_key')) {
            $this->error('❌ API key is not set.');

            return self::FAILURE;
        }

        $collections = $this->getCollections();

        if (is_int($collections)) {
            return $collections;
        }

        $queue = $this->option('queue') ?: config('vectorify.queue');

        foreach ($collections as $collection => $config) {
            $collectionId = is_int($collection) ? $config : $collection;

            $collectionSlug = ConfigResolver::getCollectionSlug($collectionId);

            $since = $this->getSince($collectionSlug);

            $this->info("Upserting collection: {$collectionSlug}" . ($since ? " (since: {$since})" : " (full)"));

            if (config('queue.default') === 'sync') {
                $this->processCollection($collectionSlug, $config, $since);
            } else {
                dispatch(new ProcessCollection(
                    collectionId: $collectionId,
                    since: $since,
                ))->onQueue($queue);

                $this->info("➡️ Collection {$collectionSlug} queued for processing");
            }
        }

        $this->info('✅ All collections upserted successfully!');

        return self::SUCCESS;
    }

    public function processCollection(
        string $collectionSlug,
        mixed $config,
        ?string $since
    ): void {
        $totalChunks = 0;

        $builder = new QueryBuilder($config, $since);

        $builder->getQuery()->chunk(
            count: 90,
            callback: function (EloquentCollection $items) use ($collectionSlug, $builder, &$totalChunks) {
                $totalChunks++;

                $this->upsert(
                    collectionSlug: $collectionSlug,
                    builder: $builder,
                    items: $items,
                );

                $this->info("➡️ {$items->count()} items processed for collection: {$collectionSlug}");

                // Free memory
                unset($items);
            }
        );

        $this->info("➡️ {$totalChunks} chunks processed for collection: {$collectionSlug}");

        if ($totalChunks > 0) {
            Cache::put(
                "vectorify:last_upsert:{$collectionSlug}",
                now()->toDateTimeString(),
                now()->addDays(30)
            );
        }
    }

    public function upsert(
        string $collectionSlug,
        QueryBuilder $builder,
        EloquentCollection $items,
    ): bool {
        $items = $items->map(function (Model $item) use ($builder) {
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
            $this->error("❌ Failed to upsert collection: {$collectionSlug}");

            return false;
        }

        return true;
    }

    public function getCollections(): array|int
    {
        $collections = config('vectorify.collections');

        if (empty($collections)) {
            $this->warn('⚠️ No collections found in the configuration file.');

            return self::SUCCESS;
        }

        // Filter collections if specific collection requested
        if ($collection = $this->option('collection')) {
            if (! isset($collections[$collection])) {
                $this->error("❌ Collection '{$collection}' not found.");

                return self::FAILURE;
            }

            $collections = [$collection => $collections[$collection]];
        }

        return $collections;
    }

    public function getSince(string $collectionSlug): ?string
    {
        if ($this->option('force')) {
            return null; // Full upsert
        }

        if ($since = $this->option('since')) {
            return $since;
        }

        return Cache::get("vectorify:last_upsert:{$collectionSlug}");
    }
}
