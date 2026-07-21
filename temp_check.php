<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$server = App\Models\Server::find(3);
if (!$server) {
    echo "server_not_found\n";
    exit;
}
echo 'server=' . $server->id . PHP_EOL;
echo 'last_scanned_at=' . ($server->last_scanned_at ? $server->last_scanned_at->toDateTimeString() : 'null') . PHP_EOL;
echo 'last_scan_status=' . ($server->last_scan_status ? $server->last_scan_status->value : 'null') . PHP_EOL;
echo 'sql_indexes=' . $server->sqlIndexes()->count() . PHP_EOL;
echo 'alerts=' . $server->alerts()->count() . PHP_EOL;
