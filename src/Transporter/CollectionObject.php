<?php

namespace Vectorify\Laravel\Transporter;

final readonly class CollectionObject
{
    public function __construct(
        public string $slug,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'metadata' => $this->metadata,
        ];
    }

    public function toPayload(): array
    {
        return [
            'slug' => $this->slug,
            'metadata' => $this->metadata,
        ];
    }
}
