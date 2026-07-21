<?php

namespace App\Services\SqlServer;

use App\Domain\Monitoring\DTO\SqlServerCapabilities;
use Illuminate\Database\Connection;
use RuntimeException;

class SqlServerCapabilityService
{
    private const QUERY = <<<'SQL'
        SET NOCOUNT ON;

        SELECT
            CONVERT(nvarchar(128), SERVERPROPERTY(N'ServerName')) AS server_name,
            CONVERT(nvarchar(128), SERVERPROPERTY(N'ProductVersion')) AS product_version,
            CONVERT(int, SERVERPROPERTY(N'ProductMajorVersion')) AS product_major_version,
            CONVERT(nvarchar(128), SERVERPROPERTY(N'Edition')) AS edition,
            CONVERT(int, SERVERPROPERTY(N'EngineEdition')) AS engine_edition,
            DB_NAME() AS database_name,
            CONVERT(int, HAS_PERMS_BY_NAME(DB_NAME(), N'DATABASE', N'VIEW DEFINITION')) AS has_view_definition,
            CONVERT(int, HAS_PERMS_BY_NAME(DB_NAME(), N'DATABASE', N'VIEW DATABASE STATE')) AS has_view_database_state,
            CONVERT(int, HAS_PERMS_BY_NAME(DB_NAME(), N'DATABASE', N'VIEW DATABASE PERFORMANCE STATE')) AS has_view_database_performance_state,
            CONVERT(int, HAS_PERMS_BY_NAME(NULL, NULL, N'VIEW SERVER STATE')) AS has_view_server_state,
            CONVERT(int, HAS_PERMS_BY_NAME(NULL, NULL, N'VIEW SERVER PERFORMANCE STATE')) AS has_view_server_performance_state,
            CONVERT(int, HAS_PERMS_BY_NAME(DB_NAME(), N'DATABASE', N'SELECT')) AS has_database_select,
            SYSUTCDATETIME() AS sampled_at_utc;
        SQL;

    public function inspect(Connection $connection): SqlServerCapabilities
    {
        $row = $connection->selectOne(self::QUERY);

        if ($row === null) {
            throw new RuntimeException('SQL Server did not return capability information.');
        }

        return SqlServerCapabilities::fromRow($row);
    }
}