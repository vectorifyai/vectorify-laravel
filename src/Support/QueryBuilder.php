<?php

namespace Vectorify\Laravel\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;
use ReflectionClass;

class QueryBuilder
{
    public EloquentBuilder $query;

    public EloquentModel $model;

    public ?string $resource;

    public array $columns = [];

    public array $relations = [];

    public array $metadata = [];

    public ?string $tenant = null;

    private array $itemData = [];

    private array $unsetItemData = [];

    protected array $configColumns = [];

    public function __construct(
        protected string|array $config,
        protected string|null $since,
    ) {
        $this->query = is_string($config)
            ? $this->resolveStringQuery($config)
            : $this->resolveCallbackQuery($config['query']);

        $this->configColumns = $config['columns'] ?? [];

        $this->model = $this->query->getModel();

        $this->resource = $this->resolveResource();

        $this->columns = $this->resolveColumns();

        $this->relations = $this->resolveRelations();

        $this->metadata = $this->resolveMetadata();

        $this->tenant = $this->resolveTenant();
    }

    public function getQuery(): EloquentBuilder
    {
        if (method_exists($this->model, 'scopeVectorify')) {
            $this->query->vectorify();
        }

        if (! empty($this->since) && method_exists($this->model, 'getUpdatedAtColumn')) {
            $this->query->where(
                column: $this->query->qualifyColumn($this->model->getUpdatedAtColumn()),
                operator: '>=',
                value: $this->since,
            );
        }

        return ! empty($this->columns)
            ? $this->query->select($this->columns)
            : $this->query;
    }

    public function getItemData(EloquentModel $item): array
    {
        if (! empty($this->resource)) {
            $data = $this->resource::make($item)
                ->response()
                ->getData();

            $data = json_decode(json_encode($data), true);

            $this->itemData = ! empty($data['data']) ? $data['data'] : $data;
        } else {
            $item->setAppends([]); // Without appends
            $this->itemData = $item->withoutRelations()->toArray();
        }

        $this->unsetItemData[$this->getKeyName()] = $this->itemData[$this->getKeyName()];
        unset($this->itemData[$this->getKeyName()]);

        foreach ($this->configColumns as $key => $col) {
            if (! is_array($col)) {
                if (is_null($this->itemData[$col])) {
                    unset($this->itemData[$col]);
                }

                continue;
            }

            $name = $key;

            if (! empty($col['alias'])) {
                $name = $col['alias'];

                $this->itemData[$name] = $this->itemData[$key];

                $this->unsetItemData[$key] = $this->itemData[$key];
                unset($this->itemData[$key]);
            }

            if (isset($col['data']) && $col['data'] === false) {
                $this->unsetItemData[$name] = $this->itemData[$key];
                unset($this->itemData[$name]);

                continue;
            }

            if (array_key_exists($name, $this->itemData) && is_null($this->itemData[$name])) {
                unset($this->itemData[$name]);

                continue;
            }

            if (empty($col['type'])) {
                continue;
            }

            if ($col['type'] === 'datetime' && ! empty($col['format'])) {
                $this->itemData[$name] = Carbon::parse($this->itemData[$name])->format($col['format']);
            }
        }

        if (empty($this->relations)) {
            return $this->itemData;
        }

        foreach ($this->relations as $rel) {
            $relColumns = $this->configColumns[$rel]['columns'];
            $relForeignKey = $this->configColumns[$rel]['foreign_key'] ?? $rel . '_id';

            $relData = $item->{$rel}?->toArray() ?? [];

            foreach ($relColumns as $relKey => $relValue) {
                if (is_array($relValue)) {
                    if (! array_key_exists($relKey, $relData)) {
                        continue;
                    }

                    if (is_null($relData[$relKey])) {
                        continue;
                    }

                    $name = $relKey;

                    if (! empty($relValue['alias'])) {
                        $name = $relValue['alias'];
                    }

                    $this->itemData[$name] = $relData[$relKey];

                    continue;
                }

                if (! array_key_exists($relValue, $relData)) {
                    continue;
                }

                if (is_null($relData[$relValue])) {
                    continue;
                }

                $this->itemData[$relValue] = $relData[$relValue];
            }

            if (array_key_exists($relForeignKey, $this->itemData)) {
                $this->unsetItemData[$relForeignKey] = $this->itemData[$relForeignKey];

                unset($this->itemData[$relForeignKey]);
            }
        }

        return $this->itemData;
    }

    public function getItemMetadata(): array
    {
        $metadata = [];

        if (empty($this->metadata)) {
            return $metadata;
        }

        foreach ($this->metadata as $key => $meta) {
            $metaKey = is_string($key) ? $key : $meta;

            if (array_key_exists($metaKey, $this->itemData) && ! is_null($this->itemData[$metaKey])) {
                $metadata[$metaKey] = $this->itemData[$metaKey];
            }

            if (array_key_exists($metaKey, $this->unsetItemData) && ! is_null($this->unsetItemData[$metaKey])) {
                $metadata[$metaKey] = $this->unsetItemData[$metaKey];
            }
        }

        return $metadata;
    }

    public function getItemTenant(): ?int
    {
        $tenant = null;

        if (empty($this->tenant)) {
            return $tenant;
        }

        if (array_key_exists($this->tenant, $this->itemData)) {
            $tenant = $this->itemData[$this->tenant];
        }

        if (! $tenant && array_key_exists($this->tenant, $this->unsetItemData)) {
            $tenant = $this->unsetItemData[$this->tenant];
        }

        return $tenant;
    }

    public function getKeyName(): ?string
    {
        // if (! in_array($this->model->getKey(), $this->model->toArray())) {
        //     throw new InvalidArgumentException('ID field is required.');
        // }

        return $this->model->getKeyName();
    }

    public function resolveStringQuery(string $model): EloquentBuilder
    {
        if (! is_subclass_of($model, EloquentModel::class)) {
            throw new InvalidArgumentException('Collection must extend Eloquent\Model class.');
        }

        return $model::query();
    }

    public function resolveCallbackQuery(callable $configQuery): EloquentBuilder
    {
        $query = $configQuery();

        if (! $query instanceof EloquentBuilder) {
            throw new InvalidArgumentException('Callback must return an Eloquent\Builder instance.');
        }

        return $query;
    }

    public function resolveResource(): ?string
    {
        if (empty($this->config['resource'])) {
            return null;
        }

        $resource = $this->config['resource'];

        if (! class_exists($resource)) {
            throw new InvalidArgumentException('Resource class does not exist.');
        }

        $reflection = new ReflectionClass($resource);

        if (! $reflection->isSubclassOf(JsonResource::class)) {
            throw new InvalidArgumentException('Resource must be an instance of JsonResource.');
        }

        return $resource;
    }

    public function resolveColumns(): array
    {
        if (! empty($this->resource)) {
            return [];
        }

        if (! empty($this->configColumns)) {
            $columns = $this->configColumns;
        } else {
            $columns = property_exists($this->model, 'vectorify')
                ? $this->model->vectorify
                : $this->model->getFillable();
        }

        $columns[] = $this->getKeyName();

        return collect($columns)
            ->map(function ($col, $key) {
                if (is_string($col)) {
                    return $col;
                }

                if (is_array($col) && array_key_exists('relationship', $col)) {
                    return $col['foreign_key'] ?? $key . '_id';
                }

                return $key;
            })
            ->flatten()
            ->toArray();
    }

    public function resolveRelations(): array
    {
        return collect($this->configColumns)
            ->filter(fn ($col) => is_array($col) && array_key_exists('relationship', $col))
            ->map(fn ($col, $key) => $key)
            ->flatten()
            ->toArray();
    }

    public function resolveMetadata(): array
    {
        if (! empty($this->config['metadata'])) {
            return $this->config['metadata'];
        }

        $metadata = collect($this->configColumns)
            ->filter(fn ($col) => is_array($col) && array_key_exists('metadata', $col))
            ->mapWithKeys(function ($col, $key) {
                $name = array_key_exists('alias', $col)
                    ? $col['alias']
                    : $key;

                $meta = [
                    'type' => ! empty($col['type']) ? $col['type'] : 'string',
                ];

                if ($meta['type'] === 'enum') {
                    $meta['options'] = $col['options'];
                }

                return [$name => $meta];
            })
            ->toArray();

        foreach ($this->relations as $rel) {
            $relColumns = $this->configColumns[$rel]['columns'];

            foreach ($relColumns as $relKey => $relValue) {
                if (! is_array($relValue)) {
                    continue;
                }

                if (! array_key_exists('metadata', $relValue)) {
                    continue;
                }

                $name = array_key_exists('alias', $relValue)
                    ? $relValue['alias']
                    : $relKey;

                $meta = [
                    'type' => ! empty($relValue['type']) ? $relValue['type'] : 'string',
                ];

                if ($meta['type'] === 'enum') {
                    $meta['options'] = $relValue['options'];
                }

                $metadata[$name] = $meta;
            }
        }

        return $metadata;
    }

    public function resolveTenant(): ?string
    {
        if (config('vectorify.tenancy') === 'single') {
            return null;
        }

        if (! empty($this->config['tenant'])) {
            return $this->config['tenant'];
        }

        $tenant = collect($this->configColumns)
            ->filter(fn ($col) => is_array($col) && array_key_exists('tenant', $col))
            ->map(fn ($col, $key) => ! empty($col['alias']) ? $col['alias'] : $key)
            ->flatten()
            ->first();

        if (empty($tenant)) {
            throw new InvalidArgumentException('Tenant column is required.');
        }

        return $tenant;
    }

    public function resolveArrayColumn(string $name): SupportCollection
    {
        return collect($this->configColumns)
            ->filter(fn ($col) => is_array($col) && array_key_exists($name, $col))
            ->map(fn ($col, $key) => $key)
            ->flatten();
    }

    public function resetItemData(): void
    {
        $this->itemData = [];
        $this->unsetItemData = [];
    }
}
