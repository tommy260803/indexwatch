<?php

namespace Tests\Unit;

use App\Models\Server;
use App\Services\SqlServer\SqlServerConnectionFactory;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Tests\TestCase;

class SqlServerConnectionFactoryTest extends TestCase
{
    public function test_it_builds_an_isolated_read_only_connection_without_db_url(): void
    {
        $database = Mockery::mock(DatabaseManager::class);
        $connection = Mockery::mock(Connection::class);
        $database->expects('purge')->with('indexwatch_sqlsrv_42');
        $database->expects('connection')->with('indexwatch_sqlsrv_42')->andReturn($connection);
        $connection->expects('getPdo')->andReturn(new \stdClass);
        $server = new Server;
        $server->forceFill([
            'id' => 42,
            'host' => 'ANTHONY',
            'port' => 1433,
            'database_name' => 'IndexWatch_Test',
            'username' => 'indexwatch_scanner',
            'password' => 'local-secret',
            'connection_options' => [
                'encrypt' => true,
                'trust_server_certificate' => true,
                'timeout' => 45,
            ],
        ]);

        $result = (new SqlServerConnectionFactory($database))->connect($server);
        $config = config('database.connections.indexwatch_sqlsrv_42');

        $this->assertSame($connection, $result);
        $this->assertNull($config['url']);
        $this->assertTrue($config['readonly']);
        $this->assertSame('ANTHONY', $config['host']);
        $this->assertSame(1433, $config['port']);
        $this->assertSame('yes', $config['encrypt']);
        $this->assertSame('yes', $config['trust_server_certificate']);
        $this->assertSame(45, $config['login_timeout']);
    }

    public function test_two_servers_use_separate_connection_names_and_configuration(): void
    {
        $database = Mockery::mock(DatabaseManager::class);
        $connectionOne = Mockery::mock(Connection::class);
        $connectionTwo = Mockery::mock(Connection::class);
        $database->expects('purge')->with('indexwatch_sqlsrv_1');
        $database->expects('connection')->with('indexwatch_sqlsrv_1')->andReturn($connectionOne);
        $connectionOne->expects('getPdo')->andReturn(new \stdClass);
        $database->expects('purge')->with('indexwatch_sqlsrv_2');
        $database->expects('connection')->with('indexwatch_sqlsrv_2')->andReturn($connectionTwo);
        $connectionTwo->expects('getPdo')->andReturn(new \stdClass);
        $factory = new SqlServerConnectionFactory($database);

        $factory->connect($this->server(1, 'SQL-ONE', 'DatabaseOne'));
        $factory->connect($this->server(2, 'SQL-TWO', 'DatabaseTwo'));

        $this->assertSame('SQL-ONE', config('database.connections.indexwatch_sqlsrv_1.host'));
        $this->assertSame('DatabaseOne', config('database.connections.indexwatch_sqlsrv_1.database'));
        $this->assertSame('SQL-TWO', config('database.connections.indexwatch_sqlsrv_2.host'));
        $this->assertSame('DatabaseTwo', config('database.connections.indexwatch_sqlsrv_2.database'));
    }

    private function server(int $id, string $host, string $database): Server
    {
        $server = new Server;
        $server->forceFill([
            'id' => $id,
            'host' => $host,
            'port' => 1433,
            'database_name' => $database,
            'username' => 'scanner_'.$id,
            'password' => 'secret-'.$id,
            'connection_options' => [],
        ]);

        return $server;
    }
}
