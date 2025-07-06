<?php

namespace Vectorify\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vectorify\Objects\CollectionObject;
use Vectorify\Objects\ItemObject;
use Vectorify\Objects\UpsertObject;

final class UpsertObjectTest extends TestCase
{
    private CollectionObject $collection;
    private array $items;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collection = new CollectionObject(
            slug: 'invoices',
            metadata: [
                'status' => ['type' => 'enum', 'options' => ['draft', 'sent', 'paid']],
                'due_at' => ['type' => 'datetime'],
            ]
        );

        $this->items = [
            new ItemObject(
                id: 1,
                data: [
                    'customer_id' => 10,
                    'amount' => 1500.00,
                    'due_at' => '2025-07-15',
                ],
                metadata: ['status' => 'sent']
            ),
            new ItemObject(
                id: 2,
                data: [
                    'customer_id' => 5,
                    'amount' => 850.00,
                    'due_at' => '2025-07-01',
                ],
                metadata: ['status' => 'paid']
            ),
            new ItemObject(
                id: 3,
                data: [
                    'customer_id' => 8,
                    'amount' => 2200.00,
                    'due_at' => '2025-08-01',
                ],
                metadata: ['status' => 'draft']
            ),
        ];
    }

    #[Test]
    public function upsert_object_creation(): void
    {
        $upsert = new UpsertObject($this->collection, $this->items);

        $this->assertEquals($this->collection, $upsert->collection);
        $this->assertEquals($this->items, $upsert->items);
    }

    #[Test]
    public function upsert_to_array_returns_correct_structure(): void
    {
        $upsert = new UpsertObject($this->collection, $this->items);

        $expected = [
            'collection' => $this->collection,
            'items' => $this->items,
        ];

        $this->assertEquals($expected, $upsert->toArray());
    }

    #[Test]
    public function upsert_to_payload_returns_correct_structure(): void
    {
        $upsert = new UpsertObject($this->collection, $this->items);

        $result = $upsert->toPayload();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('collection', $result);
        $this->assertArrayHasKey('items', $result);

        // Test collection payload
        $this->assertEquals($this->collection->toPayload(), $result['collection']);

        // Test items payload structure
        $this->assertIsArray($result['items']);
        $this->assertCount(3, $result['items']);

        foreach ($result['items'] as $index => $itemPayload) {
            $this->assertEquals($this->items[$index]->toPayload(), $itemPayload);
        }
    }

    #[Test]
    public function upsert_with_empty_items_array(): void
    {
        $upsert = new UpsertObject($this->collection, []);

        $result = $upsert->toPayload();

        $this->assertEquals([], $result['items']);
        $this->assertEquals($this->collection->toPayload(), $result['collection']);
    }

    #[Test]
    public function upsert_with_single_item(): void
    {
        $singleItem = [new ItemObject(1, ['name' => 'John Doe'])];
        $upsert = new UpsertObject($this->collection, $singleItem);

        $result = $upsert->toPayload();

        $this->assertCount(1, $result['items']);
        $this->assertEquals($singleItem[0]->toPayload(), $result['items'][0]);
    }

    #[Test]
    public function upsert_with_complex_collection_metadata(): void
    {
        $complexMetadata = [
            'status' => [
                'type' => 'enum',
                'options' => ['draft', 'review', 'published', 'archived'],
            ],
            'priority' => [
                'type' => 'enum',
                'options' => ['low', 'medium', 'high', 'urgent'],
            ],
            'created_at' => [
                'type' => 'datetime',
            ],
            'author_id' => [
                'type' => 'string',
            ],
        ];

        $collection = new CollectionObject('articles', $complexMetadata);
        $items = [
            new ItemObject(1,
                ['title' => 'Article 1', 'content' => 'Content 1'],
                ['status' => 'published', 'priority' => 'high']
            ),
        ];

        $upsert = new UpsertObject($collection, $items);
        $payload = $upsert->toPayload();

        $this->assertEquals($complexMetadata, $payload['collection']['metadata']);
        $this->assertEquals('articles', $payload['collection']['slug']);
    }

    #[Test]
    public function upsert_with_items_containing_tenant_information(): void
    {
        $items = [
            new ItemObject(1, ['data' => 'value1'], [], 100),
            new ItemObject(2, ['data' => 'value2'], [], 200),
            new ItemObject(3, ['data' => 'value3'], [], 100),
        ];

        $upsert = new UpsertObject($this->collection, $items);
        $payload = $upsert->toPayload();

        $this->assertEquals(100, $payload['items'][0]['tenant']);
        $this->assertEquals(200, $payload['items'][1]['tenant']);
        $this->assertEquals(100, $payload['items'][2]['tenant']);
    }
}
