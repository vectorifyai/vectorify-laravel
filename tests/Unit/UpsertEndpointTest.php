<?php

namespace Vectorify\Laravel\Tests\Unit;

use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Vectorify\Laravel\Transporter\Client;
use Vectorify\Laravel\Transporter\CollectionObject;
use Vectorify\Laravel\Transporter\ItemObject;
use Vectorify\Laravel\Transporter\Upsert;
use Vectorify\Laravel\Transporter\UpsertObject;

final class UpsertEndpointTest extends TestCase
{
    #[Test]
    public function upsert_send_method_calls_client_post_with_correct_parameters(): void
    {
        $mockClient = $this->createMock(Client::class);

        $collection = new CollectionObject('invoices', [
            'status' => ['type' => 'enum', 'options' => ['draft', 'sent', 'paid']],
            'due_at' => ['type' => 'datetime'],
        ]);

        $items = [
            new ItemObject(1, ['amount' => 1500.00, 'due_at' => '2025-07-15', 'customer_id' => 10], ['status' => 'sent']),
            new ItemObject(2, ['amount' => 850.00, 'due_at' => '2025-07-01', 'customer_id' => 5], ['status' => 'paid']),
        ];

        $upsertObject = new UpsertObject($collection, $items);

        $mockResponse = $this->createMock(Response::class);

        $upsert = new Upsert();

        $mockClient->expects($this->once())
                    ->method('post')
                    ->with(
                        $upsert->path,
                        $this->callback(function ($options) use ($upsertObject) {
                            $this->assertArrayHasKey('body', $options);
                            $decodedBody = json_decode($options['body'], true);
                            $expectedPayload = $upsertObject->toPayload();
                            $this->assertEquals($expectedPayload, $decodedBody);
                            return true;
                        })
                    )
                    ->willReturn($mockResponse);

        $upsert->client = $mockClient;

        $result = $upsert->send($upsertObject);

        $this->assertTrue($result);
    }

    #[Test]
    public function upsert_send_method_returns_false_when_client_returns_null(): void
    {
        // Create a mock client that returns null
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('post')->willReturn(null);

        $collection = new CollectionObject('invoices');
        $upsertObject = new UpsertObject($collection, []);

        $upsert = new Upsert();
        $upsert->client = $mockClient;

        $result = $upsert->send($upsertObject);

        $this->assertFalse($result);
    }

    #[Test]
    public function upsert_send_method_with_empty_items(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockResponse = $this->createMock(Response::class);

        $collection = new CollectionObject('invoices');
        $upsertObject = new UpsertObject($collection, []);

        $upsert = new Upsert();

        $mockClient->expects($this->once())
                    ->method('post')
                    ->with(
                        $upsert->path,
                        $this->callback(function ($options) {
                            $decodedBody = json_decode($options['body'], true);
                            $this->assertArrayHasKey('collection', $decodedBody);
                            $this->assertArrayHasKey('items', $decodedBody);
                            $this->assertEquals([], $decodedBody['items']);
                            return true;
                        })
                    )
                    ->willReturn($mockResponse);

        $upsert->client = $mockClient;

        $result = $upsert->send($upsertObject);

        $this->assertTrue($result);
    }

    #[Test]
    public function upsert_send_method_with_complex_upsert_object(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockResponse = $this->createMock(Response::class);

        $collection = new CollectionObject('invoices', [
            'status' => [
                'type' => 'enum',
                'options' => ['draft', 'sent', 'paid'],
            ],
            'due_at' => [
                'type' => 'datetime',
            ],
        ]);

        $items = [
            new ItemObject(
                'item-1',
                [
                    'amount' => 5500.00,
                    'due_at' => '2025-08-15T00:00:00Z',
                    'customer_id' => 10,
                    'tenant_id' => 123,
                    'line_items' => [
                        ['description' => 'Professional Services', 'amount' => 4000.00],
                        ['description' => 'Software License', 'amount' => 1500.00],
                    ],
                    'customer_details' => [
                        'name' => 'Acme Corporation',
                        'email' => 'billing@acme.com',
                        'phone' => '+1-555-0123',
                        'address' => '123 Business Ave'
                    ],
                ],
                [
                    'status' => 'sent',
                ],
                123, // tenant
                'https://example.com/invoices/inv-complex-001' // url
            ),
            new ItemObject(
                'item-2',
                [
                    'amount' => 1200.00,
                    'due_at' => '2025-07-30T00:00:00Z',
                    'customer_id' => 5,
                    'tenant_id' => 123,
                    'customer_details' => [
                        'name' => 'Jane Smith',
                        'email' => 'jane@example.com',
                        'phone' => '+1-555-9876',
                        'address' => '456 Main St'
                    ],
                ],
                [
                    'status' => 'draft',
                ],
                456 // tenant
            ),
        ];

        $upsertObject = new UpsertObject($collection, $items);

        $upsert = new Upsert();

        $mockClient->expects($this->once())
                    ->method('post')
                    ->with(
                        $upsert->path,
                        $this->callback(function ($options) use ($upsertObject) {
                            $decodedBody = json_decode($options['body'], true);
                            $expectedPayload = $upsertObject->toPayload();

                            // Verify collection structure
                            $this->assertEquals($expectedPayload['collection'], $decodedBody['collection']);
                            $this->assertEquals('invoices', $decodedBody['collection']['name']);
                            $this->assertArrayHasKey('metadata', $decodedBody['collection']);

                            // Verify items structure
                            $this->assertCount(2, $decodedBody['items']);
                            $this->assertEquals('item-1', $decodedBody['items'][0]['id']);
                            $this->assertEquals('item-2', $decodedBody['items'][1]['id']);
                            $this->assertEquals(123, $decodedBody['items'][0]['tenant']);
                            $this->assertEquals(456, $decodedBody['items'][1]['tenant']);

                            return true;
                        })
                    )
                    ->willReturn($mockResponse);

        $upsert->client = $mockClient;

        $result = $upsert->send($upsertObject);

        $this->assertTrue($result);
    }
}
