<?php

namespace Vectorify\Laravel\Transporter;

abstract class Endpoint
{
    public string $path;

    public Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }
}
