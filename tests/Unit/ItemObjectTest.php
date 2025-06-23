<?php

namespace Vectorify\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vectorify\Laravel\Transporter\ItemObject;

final class ItemObjectTest extends TestCase
{
    public array $data;
    public array $metadata;
    public int $tenant;
    public string $url;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data = [
            'customer_id' => 1,
            'amount' => 100,
            'due_at' => '2025-06-30',
        ];

        $this->metadata = [
            'customer_name' => 'John Doe',
            'status' => 'draft',
            'due_at' => '2025-06-30',
        ];

        $this->tenant = 123;

        $this->url = 'https://example.com/invoices/1';
    }

    #[Test]
    public function item_object_creation_with_required_parameters(): void
    {
        $item = new ItemObject(1, $this->data);

        $this->assertEquals(1, $item->id);
        $this->assertEquals($this->data, $item->data);
        $this->assertEquals([], $item->metadata);
        $this->assertNull($item->tenant);
        $this->assertNull($item->url);
    }

    #[Test]
    public function item_object_creation_with_all_parameters(): void
    {
        $item = new ItemObject(
            id: 1,
            data: $this->data,
            metadata: $this->metadata,
            tenant: $this->tenant,
            url: $this->url,
        );

        $this->assertEquals(1, $item->id);
        $this->assertEquals($this->data, $item->data);
        $this->assertEquals($this->metadata, $item->metadata);
        $this->assertEquals($this->tenant, $item->tenant);
        $this->assertEquals($this->url, $item->url);
    }

    #[Test]
    public function item_returns_correct_structure(): void
    {
        $item = new ItemObject(
            id: 1,
            data: $this->data,
            metadata: $this->metadata,
            tenant: $this->tenant,
            url: $this->url,
        );

        $expected = [
            'id' => 1,
            'data' => $this->data,
            'metadata' => $this->metadata,
            'tenant' => $this->tenant,
            'url' => $this->url,
        ];

        $this->assertEquals($expected, $item->toArray());
        $this->assertEquals($expected, $item->toPayload());
        $this->assertEquals($item->toArray(), $item->toPayload());
    }

    #[Test]
    public function item_object_with_empty_data(): void
    {
        $item = new ItemObject(1, []);

        $this->assertEquals(1, $item->id);
        $this->assertEquals([], $item->data);
        $this->assertEquals([], $item->metadata);
    }

    #[Test]
    public function item_object_with_complex_data_structure(): void
    {
        $complexInvoiceData = [
            'amount' => 7776.00,
            'due_at' => '2025-08-15',
            'customer_id' => 10,
            'customer_details' => [
                'name' => 'Acme Corporation',
                'email' => 'billing@acme.com',
                'phone' => '+1-555-0123',
                'address' => '123 Business Ave, Suite 100',
            ],
            'line_items' => [
                [
                    'description' => 'Professional Services',
                    'quantity' => 40,
                    'rate' => 150.00,
                    'amount' => 6000.00,
                ],
                [
                    'description' => 'Software License',
                    'quantity' => 1,
                    'rate' => 1200.00,
                    'amount' => 1200.00,
                ],
            ],
            'totals' => [
                'subtotal' => 7200.00,
                'tax' => 576.00,
                'total' => 7776.00,
            ],
        ];

        $item = new ItemObject(1, $complexInvoiceData);

        $this->assertEquals($complexInvoiceData, $item->data);
        $this->assertIsArray($item->data['customer_details']);
        $this->assertIsArray($item->data['line_items']);
        $this->assertIsArray($item->data['totals']);
        $this->assertEquals('Acme Corporation', $item->data['customer_details']['name']);
        $this->assertEquals(6000.00, $item->data['line_items'][0]['amount']);
        $this->assertEquals(7776.00, $item->data['totals']['total']);
        $this->assertEquals(10, $item->data['customer_id']);
    }
}
