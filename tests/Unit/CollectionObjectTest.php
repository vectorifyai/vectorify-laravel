<?php

namespace Vectorify\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vectorify\Laravel\Transporter\CollectionObject;

final class CollectionObjectTest extends TestCase
{
    public string $name;
    public array $metadata;

    protected function setUp(): void
    {
        parent::setUp();

        $this->name = 'invoices';

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
    public function collection_object_creation_with_name_only(): void
    {
        $collection = new CollectionObject($this->name);

        $this->assertEquals($this->name, $collection->name);
        $this->assertEquals([], $collection->metadata);
    }

    #[Test]
    public function collection_object_creation_with_metadata(): void
    {
        $collection = new CollectionObject($this->name, $this->metadata);

        $this->assertEquals($this->name, $collection->name);
        $this->assertEquals($this->metadata, $collection->metadata);
    }

    #[Test]
    public function collection_returns_correct_structure(): void
    {
        $collection = new CollectionObject($this->name, $this->metadata);

        $expected = [
            'name' => $this->name,
            'metadata' => $this->metadata,
        ];

        $this->assertEquals($expected, $collection->toArray());
        $this->assertEquals($expected, $collection->toPayload());
        $this->assertEquals($collection->toArray(), $collection->toPayload());
    }
}
