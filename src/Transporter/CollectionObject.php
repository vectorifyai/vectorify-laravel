<?php

namespace Vectorify\Laravel\Transporter;

final readonly class CollectionObject
{
    public function __construct(
        public string $name,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'metadata' => $this->metadata,
        ];
    }

    public function toPayload(): array
    {
        return [
            'name' => $this->name,
            'metadata' => $this->metadata,
        ];
    }
}
