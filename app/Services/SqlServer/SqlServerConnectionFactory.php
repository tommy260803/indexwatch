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
        // La integración depende del driver nativo de SQL Server.
        // Si el entorno no lo tiene, es mejor fallar de inmediato con un mensaje claro.
        if (! extension_loaded('pdo_sqlsrv')) {
            throw new RuntimeException('The pdo_sqlsrv PHP extension is required.');
        }

        // No se usa una conexión global porque cada servidor puede tener credenciales,
        // timeout y opciones TLS distintas.
        $name = $this->connectionName($server);
        $options = $server->connection_options ?? [];
        $timeout = max(1, min(300, (int) ($options['timeout'] ?? config('indexwatch.scan.statement_timeout', 60))));
        $pdoOptions = [];

        if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
            $pdoOptions[constant('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')] = $timeout;
        }

        $encrypt = $options['encrypt'] ?? config('indexwatch.sql_server.encrypt', null);
        $trustServerCertificate = $options['trust_server_certificate'] ?? config('indexwatch.sql_server.trust_server_certificate', null);

        if ($encrypt === null) {
            $encrypt = app()->environment(['local', 'testing']) ? true : true;
        }

        if ($trustServerCertificate === null) {
            $trustServerCertificate = app()->environment(['local', 'testing']);
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
            'encrypt' => $this->yesNo($encrypt),
            'trust_server_certificate' => $this->yesNo($trustServerCertificate),
            'login_timeout' => $timeout,
            'appname' => 'IndexWatch Scanner',
            'pooling' => false,
            'options' => $pdoOptions,
        ]);

        // Purge garantiza que no reusamos una conexión vieja con configuración distinta.
        $this->database->purge($name);
        $connection = $this->database->connection($name);
        $connection->getPdo();

        return $connection;
    }

    public function disconnect(Server $server): void
    {
        // Esto evita fugas de estado entre servidores y deja limpio el contenedor.
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
