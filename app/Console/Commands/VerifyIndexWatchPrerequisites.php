<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\SqlServer\SqlServerConnectionFactory;
use App\Services\SqlServer\SqlServerErrorSanitizer;
use App\Services\SqlServer\SqlServerInspectorService;
use Illuminate\Console\Command;
use Throwable;

class VerifyIndexWatchPrerequisites extends Command
{
    protected $signature = 'indexwatch:verify {server : Server ID stored in IndexWatch}';

    protected $description = 'Verify SQL Server connectivity, capabilities, and read-only DMV access';

    public function handle(
        SqlServerConnectionFactory $connections,
        SqlServerInspectorService $inspector,
        SqlServerErrorSanitizer $errors,
    ): int {
        $server = Server::query()->find($this->argument('server'));

        if ($server === null) {
            $this->error('The requested IndexWatch server does not exist.');

            return self::FAILURE;
        }

        try {
            $connection = $connections->connect($server);
            $result = $inspector->inspect($connection, $server->minimum_index_pages);

            $this->table(['Property', 'Value'], [
                ['Server', $result->capabilities->serverName],
                ['Database', $result->capabilities->databaseName],
                ['Version', $result->capabilities->productVersion],
                ['Edition', $result->capabilities->edition],
                ['Indexes visible', count($result->indexes)],
                ['Statistics visible', count($result->statistics)],
                ['Page split samples', count($result->pageSplits)],
                ['Status', $result->warnings === [] ? 'ready' : 'degraded'],
            ]);

            foreach ($result->warnings as $metric => $warning) {
                $this->warn("{$metric}: {$warning}");
            }

            return $result->warnings === [] ? self::SUCCESS : self::INVALID;
        } catch (Throwable $exception) {
            $this->error($errors->sanitize($exception, $server));

            return self::FAILURE;
        } finally {
            $connections->disconnect($server);
        }
    }
}
