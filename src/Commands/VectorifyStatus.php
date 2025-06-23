<?php

namespace Vectorify\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class VectorifyStatus extends Command
{
    protected $signature = 'vectorify:status';

    protected $description = 'Show Vectorify upsert and API status';

    public function handle(): int
    {
        $this->info('📊 Vectorify Upsert Status');
        $this->newLine();

        $collections = config('vectorify.collections');

        if (empty($collections)) {
            $this->warn('No collections configured.');
        } else {
            $headers = ['Collection', 'Last Upsert', 'Status'];
            $rows = [];

            foreach ($collections as $collection => $config) {
                $collectionName = is_int($collection) ? class_basename($config) : ucfirst($collection);

                $lastUpsert = Cache::get("vectorify:last_upsert:{$collectionName}");

                $rows[] = [
                    $collectionName,
                    $lastUpsert ? Carbon::parse($lastUpsert)->diffForHumans() : 'Never',
                    $lastUpsert ? '✅ Upserted' : '⏳ Pending'
                ];
            }

            $this->table($headers, $rows);
        }

        if ($rateLimitInfo = Cache::get('vectorify:api:rate_limit')) {
            $this->newLine();

            $this->info('🔄 Vectorify API Rate Limit Status:');

            $remaining = $rateLimitInfo['remaining'] ?? 'Unknown';
            $resetTime = isset($rateLimitInfo['reset_time'])
                ? Carbon::parse($rateLimitInfo['reset_time'])->diffForHumans()
                : 'Unknown';

            $this->line("• Remaining requests: {$remaining}");
            $this->line("• Reset time: {$resetTime}");
        }

        return self::SUCCESS;
    }
}
