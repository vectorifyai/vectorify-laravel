<?php

namespace Vectorify\Laravel\Transporter;

class Query extends Endpoint
{
    public string $path = 'query';

    public function send(QueryObject $object): bool|array
    {
        $response = $this->client->post($this->path, [
            'body' => json_encode($object->toPayload()),
        ]);

        if (is_null($response)) {
            return false;
        }

        return $response->json();
    }
}
