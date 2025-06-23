<?php

namespace Vectorify\Laravel\Transporter;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Client
{
    public string $baseUrl = 'https://api.vectorify.ai/';

    public function get(string $path, array $options = []): ?Response
    {
        return $this->request('GET', $path, $options);
    }

    public function post(string $path, array $options = []): ?Response
    {
        return $this->request('POST', $path, $options);
    }

    public function put(string $path, array $options = []): ?Response
    {
        return $this->request('PUT', $path, $options);
    }

    public function patch(string $path, array $options = []): ?Response
    {
        return $this->request('PATCH', $path, $options);
    }

    public function delete(string $path, array $options = []): ?Response
    {
        return $this->request('DELETE', $path, $options);
    }

    public function request(string $method, string $path, array $options = []): ?Response
    {
        $this->checkRateLimit();

        $client = $this->getHttpClient();

        try {
            $response = $client->send($method, $path, $options);

            $this->updateRateLimit($response);
        } catch (Exception $e) {
            report($e);

            return null;
        }

        if (! $response->successful()) {
            report($response->body());

            return null;
        }

        if ($method === 'GET' && ! $response->ok()) {
            report($response->body());

            return null;
        }

        if ($method === 'POST' && ! $response->created()) {
            report($response->body());

            return null;
        }

        if ($method === 'DELETE' && ! $response->noContent()) {
            report($response->body());

            return null;
        }

        return $response;
    }

    public function getHttpClient(): PendingRequest
    {
        return Http::acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                'Api-Key' => (string) config('vectorify.api_key'),
            ])
            ->baseUrl($this->baseUrl)
            ->timeout((int) config('vectorify.timeout'))
            ->throw()
            ->retry(3, fn (int $attempt, Exception $e) => $attempt * 300);
    }

    private function checkRateLimit(): void
    {
        $rateLimit = Cache::get('vectorify:api:rate_limit');

        if (! $rateLimit || ! isset($rateLimit['remaining']) || $rateLimit['remaining'] > 1) {
            return;
        }

        /** @var \Illuminate\Support\Carbon $resetTime */
        $resetTime = $rateLimit['reset_time'];
        $waitTime = $resetTime->diffInSeconds(now());

        if ($waitTime <= 0) {
            return;
        }

        Log::info("Rate limit reached, waiting {$waitTime} seconds");

        sleep(min($waitTime, 120)); // Max 2 minute wait
    }

    private function updateRateLimit(Response $response): void
    {
        $rateLimit = [
            'remaining' => (int) $response->header('X-RateLimit-Remaining'),
        ];

        $rateLimit['reset_time'] = $response->header('Retry-After')
            ? now()->addSeconds((int) $response->header('Retry-After'))
            : now()->addMinute();

        Cache::put('vectorify:api:rate_limit', $rateLimit, $rateLimit['reset_time']);
    }
}
