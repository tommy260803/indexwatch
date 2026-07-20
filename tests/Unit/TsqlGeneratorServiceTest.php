<?php

namespace Tests\Unit;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\RecommendedAction;
use App\Models\Alert;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Services\Maintenance\TsqlGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TsqlGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private TsqlGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TsqlGeneratorService;
    }

    private function createAlertWithIndex(
        string $alertType = 'fragmentation',
        string $action = 'REBUILD',
        array $indexOverrides = [],
        array $alertOverrides = [],
        ?Server $server = null,
    ): Alert {
        $server ??= Server::factory()->create([
            'sql_server_capabilities' => ['supports_online_index_operations' => false],
        ]);

        $index = SqlIndex::factory()->create(array_merge([
            'server_id' => $server->id,
            'schema_name' => 'dbo',
            'table_name' => 'Users',
            'index_name' => 'IX_Users_Email',
            'fill_factor' => 90,
        ], $indexOverrides));

        return Alert::factory()->create(array_merge([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'alert_type' => $alertType,
            'severity' => AlertSeverity::Warning,
            'status' => AlertStatus::Pending,
            'recommended_action' => $action,
        ], $alertOverrides));
    }

    // --------------------------------------------------------- escapeIdentifier

    #[Test]
    public function test_escape_identifier_wraps_in_brackets(): void
    {
        $this->assertSame('[dbo]', $this->service->escapeIdentifier('dbo'));
        $this->assertSame('[IX_Users_Email]', $this->service->escapeIdentifier('IX_Users_Email'));
    }

    #[Test]
    public function test_escape_identifier_strips_existing_brackets(): void
    {
        $this->assertSame('[dbo]', $this->service->escapeIdentifier('[dbo]'));
        $this->assertSame('[dbo]', $this->service->escapeIdentifier('[[dbo]]'));
    }

    #[Test]
    public function test_escape_identifier_rejects_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->escapeIdentifier('');
    }

    #[Test]
    public function test_escape_identifier_rejects_long_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->escapeIdentifier(str_repeat('a', 129));
    }

    // --------------------------------------------------------- REBUILD

    #[Test]
    public function test_generate_rebuild(): void
    {
        $alert = $this->createAlertWithIndex('fragmentation', 'REBUILD');
        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('ALTER INDEX [IX_Users_Email] ON [dbo].[Users] REBUILD', $sql);
        $this->assertStringContainsString('FILLFACTOR = 90', $sql);
        $this->assertStringContainsString('MAXDOP = 1', $sql);
        $this->assertStringNotContainsString('ONLINE = ON', $sql);
    }

    #[Test]
    public function test_generate_rebuild_with_online(): void
    {
        $server = Server::factory()->create([
            'sql_server_capabilities' => ['supports_online_index_operations' => true],
        ]);
        $alert = $this->createAlertWithIndex('fragmentation', 'REBUILD', server: $server);
        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('ONLINE = ON', $sql);
    }

    // --------------------------------------------------------- REORGANIZE

    #[Test]
    public function test_generate_reorganize(): void
    {
        $alert = $this->createAlertWithIndex('fragmentation', 'REORGANIZE', alertOverrides: [
            'recommended_action' => RecommendedAction::Reorganize,
        ]);
        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('ALTER INDEX [IX_Users_Email] ON [dbo].[Users] REORGANIZE', $sql);
        $this->assertStringContainsString('LOB_COMPACTION = ON', $sql);
    }

    // --------------------------------------------------------- UPDATE STATISTICS

    #[Test]
    public function test_generate_update_statistics(): void
    {
        $alert = $this->createAlertWithIndex('stale_statistics', 'UPDATE STATISTICS', alertOverrides: [
            'recommended_action' => RecommendedAction::UpdateStatistics,
        ]);
        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('UPDATE STATISTICS [dbo].[Users] [IX_Users_Email] WITH FULLSCAN', $sql);
    }

    // --------------------------------------------------------- CREATE INDEX

    #[Test]
    public function test_generate_create_index(): void
    {
        $server = Server::factory()->create();
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'alert_type' => AlertType::MissingIndex,
            'status' => AlertStatus::Pending,
            'recommended_action' => RecommendedAction::CreateIndex,
            'metadata' => [
                'schema_name' => 'dbo',
                'table_name' => 'Orders',
                'equality_columns' => ['CustomerID'],
                'inequality_columns' => ['OrderDate'],
                'included_columns' => ['TotalAmount'],
            ],
        ]);

        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('CREATE NONCLUSTERED INDEX', $sql);
        $this->assertStringContainsString('ON [dbo].[Orders]', $sql);
        $this->assertStringContainsString('[CustomerID] ASC', $sql);
        $this->assertStringContainsString('[OrderDate] ASC', $sql);
        $this->assertStringContainsString('INCLUDE ([TotalAmount])', $sql);
    }

    #[Test]
    public function test_generate_create_index_insufficient_metadata(): void
    {
        $server = Server::factory()->create();
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'alert_type' => AlertType::MissingIndex,
            'status' => AlertStatus::Pending,
            'recommended_action' => RecommendedAction::CreateIndex,
            'metadata' => ['schema_name' => 'dbo'],
        ]);

        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('-- CREATE INDEX: insufficient metadata', $sql);
    }

    // --------------------------------------------------------- DISABLE INDEX

    #[Test]
    public function test_generate_disable_index(): void
    {
        $alert = $this->createAlertWithIndex('inactive', 'DISABLE INDEX', alertOverrides: [
            'recommended_action' => RecommendedAction::DisableIndex,
        ]);
        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('ALTER INDEX [IX_Users_Email] ON [dbo].[Users] DISABLE', $sql);
    }

    // --------------------------------------------------------- DROP INDEX

    #[Test]
    public function test_generate_drop_index(): void
    {
        $alert = $this->createAlertWithIndex('duplicate_index', 'DROP INDEX', alertOverrides: [
            'recommended_action' => RecommendedAction::DropIndex,
        ]);
        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('DROP INDEX [IX_Users_Email] ON [dbo].[Users]', $sql);
    }

    // --------------------------------------------------------- CREATE CLUSTERED

    #[Test]
    public function test_generate_create_clustered(): void
    {
        $alert = $this->createAlertWithIndex('heap', 'CREATE CLUSTERED', alertOverrides: [
            'recommended_action' => RecommendedAction::CreateClustered,
            'subject_type' => SqlIndex::class,
        ]);
        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('CREATE CLUSTERED INDEX', $sql);
        $this->assertStringContainsString('ON [dbo].[Users]', $sql);
    }

    // --------------------------------------------------------- edge cases

    #[Test]
    public function test_generate_throws_without_action(): void
    {
        $server = Server::factory()->create();
        $index = SqlIndex::factory()->create(['server_id' => $server->id]);
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => $index->id,
            'recommended_action' => null,
            'responded_action' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->generate($alert);
    }

    #[Test]
    public function test_generate_throws_for_unsupported_action(): void
    {
        $alert = $this->createAlertWithIndex('fragmentation', 'REBUILD');
        $alert->forceFill(['recommended_action' => RecommendedAction::Review])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->service->generate($alert);
    }

    #[Test]
    public function test_generate_throws_when_index_required_but_missing(): void
    {
        $server = Server::factory()->create();
        $alert = Alert::factory()->create([
            'server_id' => $server->id,
            'sql_index_id' => null,
            'alert_type' => 'fragmentation',
            'recommended_action' => RecommendedAction::Rebuild,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->generate($alert);
    }

    #[Test]
    public function test_fill_factor_is_clamped(): void
    {
        $alert = $this->createAlertWithIndex('fragmentation', 'REBUILD', indexOverrides: [
            'fill_factor' => null,
        ]);
        $sql = $this->service->generate($alert);

        // null fill_factor defaults to 90
        $this->assertStringContainsString('FILLFACTOR = 90', $sql);
    }

    #[Test]
    public function test_index_name_truncation(): void
    {
        $longTable = str_repeat('Tab', 40); // 120 chars, under 128 limit for table name
        $alert = Alert::factory()->create([
            'alert_type' => AlertType::MissingIndex,
            'status' => AlertStatus::Pending,
            'recommended_action' => RecommendedAction::CreateIndex,
            'metadata' => [
                'schema_name' => 'dbo',
                'table_name' => $longTable,
                'equality_columns' => ['Col1'],
            ],
        ]);

        $sql = $this->service->generate($alert);

        $this->assertStringContainsString('CREATE NONCLUSTERED INDEX', $sql);
        $this->assertStringContainsString("[{$longTable}]", $sql);
        // Generated index name should be truncated to fit 128 chars total
        preg_match('/CREATE NONCLUSTERED INDEX \[([^\]]+)\]/', $sql, $m);
        $this->assertNotEmpty($m);
        $this->assertLessThanOrEqual(128, strlen($m[1]));
    }
}
