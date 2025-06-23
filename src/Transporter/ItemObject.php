<?php

namespace Vectorify\Laravel\Transporter;

final readonly class ItemObject
{
    public function __construct(
        public string $id,
        public array $data,
        public array $metadata = [],
        public ?int $tenant = null,
        public ?string $url = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'tenant' => $this->tenant,
            'url' => $this->url,
        ];
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'tenant' => $this->tenant,
            'url' => $this->url,
        ];
    }
}
