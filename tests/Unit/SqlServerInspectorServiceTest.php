<?php

namespace Tests\Unit;

use App\Domain\Monitoring\DTO\SqlServerCapabilities;
use App\Services\SqlServer\SqlServerCapabilityService;
use App\Services\SqlServer\SqlServerInspectorService;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SqlServerInspectorServiceTest extends TestCase
{
    public function test_it_merges_inventory_with_optional_metrics(): void
    {
        $connection = Mockery::mock(Connection::class);
        $capabilityService = Mockery::mock(SqlServerCapabilityService::class);
        $capabilityService->expects('inspect')->with($connection)->andReturn($this->capabilities());
        $connection->expects('select')->times(6)->andReturnUsing(function (string $query): array {
            return match (true) {
                str_contains($query, 'idx.type_desc AS index_type') => [(object) [
                    'schema_name' => 'dbo', 'table_name' => 'orders', 'object_id' => 10,
                    'index_id' => 2, 'index_name' => 'IX_orders', 'index_type' => 'NONCLUSTERED',
                    'is_unique' => 0, 'is_primary_key' => 0, 'is_disabled' => 0, 'fill_factor' => 0,
                ]],
                str_contains($query, 'WITH physical AS') => [(object) [
                    'object_id' => 10, 'index_id' => 2, 'page_count' => 2000,
                    'size_mb' => '15.63', 'fragmentation_percent' => '31.50',
                ]],
                str_contains($query, 'sqlserver_start_time') => [(object) [
                    'object_id' => 10, 'index_id' => 2, 'user_seeks' => 100,
                    'user_scans' => 2, 'user_lookups' => 3, 'user_updates' => 10,
                    'last_user_seek_at' => null, 'last_user_scan_at' => null,
                    'last_user_lookup_at' => null, 'sqlserver_start_time' => '2026-07-01 10:00:00',
                ]],
                str_contains($query, 'sys.dm_db_stats_properties') => [],
                str_contains($query, 'sys.dm_db_index_operational_stats') => [],
                str_contains($query, 'sys.dm_db_missing_index_groups') => [],
                default => $this->fail('Unexpected SQL query.'),
            };
        });

        $result = (new SqlServerInspectorService($capabilityService))->inspect($connection, 1000);

        $this->assertCount(1, $result->indexes);
        $this->assertSame(100, $result->indexes[0]->fillFactor);
        $this->assertSame(31.5, $result->indexes[0]->fragmentationPercent);
        $this->assertSame(105, $result->indexes[0]->totalReads());
        $this->assertSame('2026-07-01', $result->serverStartedAt?->format('Y-m-d'));
        $this->assertSame([], $result->warnings);
        $this->assertTrue($result->capabilities->supportsOnlineIndexOperations());
        $this->assertTrue($result->capabilities->supportsResumableIndexOperations());
    }

    #[DataProvider('readOnlyQueryProvider')]
    public function test_all_inspection_queries_are_read_only(string $constant): void
    {
        $reflection = new \ReflectionClass(SqlServerInspectorService::class);
        $query = $reflection->getReflectionConstant($constant)->getValue();

        $this->assertDoesNotMatchRegularExpression(
            '/\b(?:ALTER|CREATE|DROP|TRUNCATE|INSERT|UPDATE|DELETE|MERGE|EXEC(?:UTE)?)\b/i',
            $query,
        );
    }

    public static function readOnlyQueryProvider(): array
    {
        return [
            ['INVENTORY_QUERY'],
            ['FRAGMENTATION_QUERY'],
            ['USAGE_QUERY'],
            ['STATISTICS_QUERY'],
            ['PAGE_SPLITS_QUERY'],
        ];
    }

    private function capabilities(): SqlServerCapabilities
    {
        return new SqlServerCapabilities(
            serverName: 'ANTHONY',
            databaseName: 'IndexWatch_Test',
            productVersion: '16.0.1000.6',
            productMajorVersion: 16,
            edition: 'Developer Edition (64-bit)',
            engineEdition: 3,
            hasViewDefinition: true,
            hasViewDatabaseState: true,
            hasViewDatabasePerformanceState: true,
            hasViewServerState: true,
            hasViewServerPerformanceState: true,
            hasDatabaseSelect: true,
            sampledAt: new DateTimeImmutable,
        );
    }
}
