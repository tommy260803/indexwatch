<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$server = App\Models\Server::find(3);
if ($server) {
    echo 'server=' . $server->name . PHP_EOL;
    $status = $server->last_scan_status;
    echo 'last_scan_status=' . ($status instanceof UnitEnum ? $status->name : ($status ?? 'null')) . PHP_EOL;
    echo 'last_scanned_at=' . ($server->last_scanned_at ? $server->last_scanned_at->toDateTimeString() : 'null') . PHP_EOL;
    echo 'sql_indexes=' . ($server->sql_indexes ?? 'null') . PHP_EOL;
    echo 'alerts=' . ($server->alerts ?? 'null') . PHP_EOL;
} else {
    echo 'not-found' . PHP_EOL;
}
