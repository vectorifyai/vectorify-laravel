<?php

namespace Vectorify\Laravel\Transporter;

class Upsert extends Endpoint
{
    public string $path = 'upserts';

    public function send(UpsertObject $object): bool
    {
        $response = $this->client->post($this->path, [
            'body' => json_encode($object->toPayload()),
        ]);

        return ! is_null($response);
    }
}
