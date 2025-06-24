<?php

namespace Vectorify\Laravel\Transporter;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Client
{
    public string $baseUrl = 'https://api.vectorify.ai/v1/';

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
        return retry(
            times: 3,
            callback: function () use ($method, $path, $options) {
                $this->checkRateLimit();

                $client = $this->getHttpClient();

                $response = $client->send($method, $path, $options);

                $this->updateRateLimit($response);

                // Handle rate limit responses specifically
                if ($response->status() === 429) {
                    Log::warning('Rate limit exceeded, will retry after waiting', [
                        'status' => $response->status(),
                        'headers' => $response->headers(),
                    ]);

                    $this->handleRateLimitResponse($response);

                    throw new Exception('Rate limit exceeded, retrying after backoff');
                }

                // Throw exception for server errors to trigger retry
                if ($response->status() >= 500) {
                    Log::warning('Server error encountered, will retry', [
                        'status' => $response->status(),
                    ]);

                    throw new Exception("Server error: {$response->status()}");
                }

                if (! $response->successful()) {
                    report($response->body());

                    return null;
                }

                return $response;
            },
            sleepMilliseconds: function (int $attempt, Exception $exception) {
                // Exponential backoff for non-rate-limit errors
                if (! str_contains($exception->getMessage(), 'Too Many Attempts')) {
                    $backoffTime = min(pow(2, $attempt - 1), 60);

                    Log::info("Retrying request in {$backoffTime} seconds", [
                        'attempt' => $attempt,
                        'exception' => $exception->getMessage(),
                    ]);

                    return $backoffTime * 1000; // Convert to milliseconds
                }

                return 0; // No additional delay for rate limit (already handled)
            }
        );
    }

    public function getHttpClient(): PendingRequest
    {
        return Http::acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                'Api-Key' => (string) config('vectorify.api_key'),
            ])
            ->baseUrl($this->baseUrl)
            ->timeout((int) config('vectorify.timeout'));
    }

    private function checkRateLimit(): void
    {
        $rateLimit = Cache::get('vectorify:api:rate_limit');

        if (! $rateLimit || ! isset($rateLimit['remaining'])) {
            return;
        }

        // Be more aggressive - start rate limiting when we have few requests left
        if ($rateLimit['remaining'] > 2) {
            return;
        }

        /** @var Carbon $resetTime */
        $resetTime = $rateLimit['reset_time'];
        $waitTime = $resetTime->diffInSeconds(now());

        if ($waitTime <= 0) {
            Cache::forget('vectorify:api:rate_limit');

            return;
        }

        // Add progressive delays based on remaining requests
        $delayTime = match (true) {
            $rateLimit['remaining'] <= 0 => min($waitTime, 90), // Full wait if no requests left
            $rateLimit['remaining'] <= 2 => min($waitTime / 2, 30), // Half wait if very few left
            $rateLimit['remaining'] <= 5 => min($waitTime / 4, 10), // Quarter wait if low
            default => 0,
        };

        if ($delayTime > 0) {
            Log::info("Rate limit preventive delay: {$delayTime} seconds (remaining: {$rateLimit['remaining']})");

            sleep((int) $delayTime);
        }
    }

    private function updateRateLimit(Response $response): void
    {
        $remaining = $this->getHeader('X-RateLimit-Remaining', $response);

        if ($remaining === null) {
            return;
        }

        $rateLimit = [
            'remaining' => (int) $remaining,
        ];

        $retryAfter = $this->getHeader('Retry-After', $response);

        $waitTime = $retryAfter ? (int) $retryAfter : 90;

        $rateLimit['reset_time'] = now()->addSeconds($waitTime);

        Cache::put('vectorify:api:rate_limit', $rateLimit, $rateLimit['reset_time']);

        Log::debug('Rate limit updated', [
            'remaining' => $rateLimit['remaining'],
            'reset_time' => $rateLimit['reset_time']->toISOString(),
        ]);
    }

    private function handleRateLimitResponse(Response $response): void
    {
        $retryAfter = $this->getHeader('Retry-After', $response);

        $waitTime = $retryAfter ? (int) $retryAfter : 90;

        // Update rate limit cache to reflect we've hit the limit
        $rateLimit = [
            'remaining' => 0,
            'reset_time' => now()->addSeconds($waitTime),
        ];

        Cache::put('vectorify:api:rate_limit', $rateLimit, $rateLimit['reset_time']);

        Log::info("Rate limit hit, waiting {$waitTime} seconds before retry");

        sleep(min($waitTime, 90)); // Max 90 seconds wait
    }

    private function getHeader(string $name, Response $response): ?string
    {
        // Try multiple common header formats
        return $response->header($name)
            ?? $response->header(strtolower($name));
    }
}
