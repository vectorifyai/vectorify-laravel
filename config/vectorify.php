<?php

return [

    'api_key' => env('VECTORIFY_API_KEY'),

    'tenancy' => env('VECTORIFY_TENANCY', 'single'),

    'queue' => env('VECTORIFY_QUEUE', 'default'),

    'timeout' => env('VECTORIFY_TIMEOUT', 300),

    'collections' => [
        // \App\Models\Invoice::class,
        // 'invoices' => [
        //     'query' => fn () => \App\Models\Invoice::query()->with('customer'),
        //     'resource' => \App\Http\Resources\InvoiceResource::class,
        //     'metadata' => [
        //         'customer_name' => [
        //             'type' => 'string',
        //         ],
        //         'status' => [
        //             'type' => 'enum',
        //             'options' => ['draft', 'sent', 'paid'],
        //         ],
        //         'due_date' => [
        //             'type' => 'datetime',
        //         ],
        //     ],
        // ],
        // 'invoices' => [
        //     'query' => fn () => \App\Models\Invoice::query()->with('customer'),
        //     'columns' => [
        //         'customer' => [
        //             'relationship' => true,
        //             'columns' => [
        //                 'name' => [
        //                     'alias' => 'customer_name',
        //                     'metadata' => true,
        //                     'type' => 'string',
        //                 ],
        //             ],
        //         ],
        //         'status' => [
        //             'metadata' => true,
        //             'type' => 'enum',
        //             'options' => ['draft', 'sent', 'paid'],
        //         ],
        //         'amount',
        //         'currency_code' => [
        //             'alias' => 'currency',
        //         ],
        //         'due_at' => [
        //             'alias' => 'due_date',
        //             'format' => 'Y-m-d',
        //             'metadata' => true,
        //             'type' => 'datetime',
        //         ],
        //     ],
        // ],
    ],

];
