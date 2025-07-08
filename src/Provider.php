<?php

namespace Vectorify\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Stringable;
use Vectorify\GuzzleRateLimiter\Stores\LaravelStore;
use Vectorify\Laravel\Commands\VectorifyStatus;
use Vectorify\Laravel\Commands\VectorifyUpsert;
use Vectorify\Vectorify;

class Provider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/vectorify.php', 'vectorify'
        );

        $this->app->singleton(Vectorify::class, function (Application $app) {
            $apiKey = config('vectorify.api_key');
            $timeout = config('vectorify.timeout', 300);

            if (empty($apiKey)) {
                throw new \InvalidArgumentException(
                    message: 'Vectorify API key is required. Please set VECTORIFY_API_KEY environment variable.'
                );
            }

            // Create Laravel cache store for rate limiting
            $store = new LaravelStore(
                $app->make('cache')->store(),
                'vectorify:api:rate_limit'
            );

            return new Vectorify($apiKey, $timeout, $store);
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/vectorify.php' => config_path('vectorify.php'),
        ], 'vectorify');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            VectorifyStatus::class,
            VectorifyUpsert::class,
        ]);

        $this->app->make(Schedule::class)
            ->command(VectorifyUpsert::class)
            ->everySixHours()
            ->runInBackground()
            ->onFailure(function (Stringable $output) {
                report('Vectorify Upsert failed: ' . $output);
            });
    }
}
