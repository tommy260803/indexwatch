<?php

namespace App\Services\SqlServer;

use App\Models\Server;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

class SqlServerConnectionFactory
{
    public function __construct(private readonly DatabaseManager $database) {}

    public function connect(Server $server): Connection
    {
        if (! extension_loaded('pdo_sqlsrv')) {
            throw new RuntimeException('The pdo_sqlsrv PHP extension is required.');
        }

        $name = $this->connectionName($server);
        $options = $server->connection_options ?? [];
        $timeout = max(1, min(300, (int) ($options['timeout'] ?? config('indexwatch.scan.statement_timeout', 60))));
        $pdoOptions = [];

        if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
            $pdoOptions[constant('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')] = $timeout;
        }

        config()->set("database.connections.{$name}", [
            'driver' => 'sqlsrv',
            'url' => null,
            'host' => $server->host,
            'port' => $server->port,
            'database' => $server->database_name,
            'username' => $server->username,
            'password' => $server->password,
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'readonly' => true,
            'encrypt' => $this->yesNo($options['encrypt'] ?? true),
            'trust_server_certificate' => $this->yesNo($options['trust_server_certificate'] ?? false),
            'login_timeout' => $timeout,
            'appname' => 'IndexWatch Scanner',
            'pooling' => false,
            'options' => $pdoOptions,
        ]);

        $this->database->purge($name);
        $connection = $this->database->connection($name);
        $connection->getPdo();

        return $connection;
    }

    public function disconnect(Server $server): void
    {
        $name = $this->connectionName($server);
        $this->database->purge($name);
        config()->set("database.connections.{$name}", null);
    }

    public function connectionName(Server $server): string
    {
        return 'indexwatch_sqlsrv_'.$server->getKey();
    }

    private function yesNo(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? 'yes' : 'no';
    }
}
