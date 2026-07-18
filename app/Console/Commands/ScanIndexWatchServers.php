<?php

namespace App\Console\Commands;

use App\Jobs\ScanServerJob;
use App\Models\Server;
use Illuminate\Console\Command;

class ScanIndexWatchServers extends Command
{
    protected $signature = 'indexwatch:scan
        {--server= : Scan only the given server ID}
        {--sync : Run immediately instead of dispatching to the scans queue}';

    protected $description = 'Dispatch read-only SQL Server scans for active IndexWatch servers';

    public function handle(): int
    {
        $query = Server::query()->active()->orderBy('id');

        if ($serverId = $this->option('server')) {
            $query->whereKey($serverId);
        }

        $servers = $query->get();

        if ($servers->isEmpty()) {
            $this->warn('No active servers matched the scan request.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($servers as $server) {
            if ($this->option('sync')) {
                try {
                    ScanServerJob::dispatchSync($server->id);
                    $this->info("Scanned server {$server->id}: {$server->name}");
                } catch (\Throwable) {
                    $failures++;
                    $this->error("Scan failed for server {$server->id}: {$server->name}. Check the sanitized scan record.");
                }
            } else {
                ScanServerJob::dispatch($server->id);
                $this->info("Queued server {$server->id}: {$server->name}");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
