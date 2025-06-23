<?php

namespace Vectorify\Laravel\Transporter;

final readonly class QueryObject
{
    public function __construct(
        public string $text,
        public int $limit = 50,
        public array $filter = [],
    ) {}

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'limit' => $this->limit,
            'filter' => $this->filter,
        ];
    }

    public function toPayload(): array
    {
        return [
            'text' => $this->text,
            'limit' => $this->limit,
            'filter' => $this->filter,
        ];
    }
}
