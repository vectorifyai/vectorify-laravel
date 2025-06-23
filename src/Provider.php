<?php

namespace Vectorify\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Stringable;
use Vectorify\Laravel\Commands\VectorifyUpsert;
use Vectorify\Laravel\Commands\VectorifyStatus;

class Provider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/vectorify.php', 'vectorify'
        );
    }

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/vectorify.php' => config_path('vectorify.php'),
        ]);

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
