<?php

namespace Vectorify\Laravel\Transporter;

final readonly class UpsertObject
{
    public function __construct(
        public CollectionObject $collection,
        public array $items,
    ) {}

    public function toArray(): array
    {
        return [
            'collection' => $this->collection,
            'items' => $this->items,
        ];
    }

    public function toPayload(): array
    {
        return [
            'collection' => $this->collection->toPayload(),
            'items' => array_map(fn (ItemObject $i) => $i->toPayload(), $this->items),
        ];
    }
}
