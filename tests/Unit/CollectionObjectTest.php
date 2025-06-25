<?php

namespace Vectorify\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vectorify\Laravel\Transporter\CollectionObject;

final class CollectionObjectTest extends TestCase
{
    public string $slug;
    public array $metadata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slug = 'invoices';

        $this->metadata = [
            'customer_name' => [
                'type' => 'string',
            ],
            'status' => [
                'type' => 'enum',
                'options' => ['draft', 'sent', 'paid'],
            ],
            'due_at' => [
                'type' => 'datetime',
            ],
        ];
    }

    #[Test]
    public function collection_object_creation_with_slug_only(): void
    {
        $collection = new CollectionObject($this->slug);

        $this->assertEquals($this->slug, $collection->slug);
        $this->assertEquals([], $collection->metadata);
    }

    #[Test]
    public function collection_object_creation_with_metadata(): void
    {
        $collection = new CollectionObject($this->slug, $this->metadata);

        $this->assertEquals($this->slug, $collection->slug);
        $this->assertEquals($this->metadata, $collection->metadata);
    }

    #[Test]
    public function collection_returns_correct_structure(): void
    {
        $collection = new CollectionObject($this->slug, $this->metadata);

        $expected = [
            'slug' => $this->slug,
            'metadata' => $this->metadata,
        ];

        $this->assertEquals($expected, $collection->toArray());
        $this->assertEquals($expected, $collection->toPayload());
        $this->assertEquals($collection->toArray(), $collection->toPayload());
    }
}
