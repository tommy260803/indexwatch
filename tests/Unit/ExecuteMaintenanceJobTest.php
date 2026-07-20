<?php

namespace Tests\Unit;

use App\Enums\AlertStatus;
use App\Enums\MaintenanceStatus;
use App\Jobs\ExecuteMaintenanceJob;
use App\Jobs\ScanServerJob;
use App\Models\Alert;
use App\Models\MaintenanceAction;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Services\Maintenance\MaintenanceWindowResolver;
use App\Services\Maintenance\TsqlGeneratorService;
use App\Services\SqlServer\SqlServerConnectionFactory;
use App\Services\SqlServer\SqlServerErrorSanitizer;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecuteMaintenanceJobTest extends TestCase
{
    use RefreshDatabase;

    private Server $server;

    private SqlIndex $index;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'indexwatch.maintenance.lock_store' => 'array',
        ]);

        $this->server = Server::factory()->create([
            'status' => 'active',
            'sql_server_capabilities' => ['supports_online_index_operations' => false],
        ]);

        $this->index = SqlIndex::factory()->create([
            'server_id' => $this->server->id,
            'schema_name' => 'dbo',
            'table_name' => 'Users',
            'index_name' => 'IX_Users_Email',
            'fill_factor' => 90,
        ]);
    }

    private function createApprovedAlert(array $overrides = []): Alert
    {
        return Alert::factory()->approved()->create(array_merge([
            'server_id' => $this->server->id,
            'sql_index_id' => $this->index->id,
            'alert_type' => 'fragmentation',
            'recommended_action' => 'REBUILD',
            'severity' => 'warning',
        ], $overrides));
    }

    private function mockSuccessfulExecution(): void
    {
        $mockConnection = Mockery::mock(Connection::class);
        $mockConnection->shouldReceive('statement')->once();

        $mockFactory = Mockery::mock(SqlServerConnectionFactory::class);
        $mockFactory->shouldReceive('connect')->once()->andReturn($mockConnection);
        $mockFactory->shouldReceive('disconnect')->once();

        $this->app->instance(SqlServerConnectionFactory::class, $mockFactory);
        $this->app->instance(WhatsAppService::class, Mockery::mock(WhatsAppService::class));
        $this->app->instance(SqlServerErrorSanitizer::class, new SqlServerErrorSanitizer);
    }

    private function mockFailingExecution(string $errorMessage = 'Deadlock detected'): void
    {
        $mockConnection = Mockery::mock(Connection::class);
        $mockConnection->shouldReceive('statement')->once()->andThrow(
            new \RuntimeException($errorMessage),
        );

        $mockFactory = Mockery::mock(SqlServerConnectionFactory::class);
        $mockFactory->shouldReceive('connect')->once()->andReturn($mockConnection);
        $mockFactory->shouldReceive('disconnect')->once();

        $this->app->instance(SqlServerConnectionFactory::class, $mockFactory);
        $this->app->instance(WhatsAppService::class, Mockery::mock(WhatsAppService::class));
        $this->app->instance(SqlServerErrorSanitizer::class, new SqlServerErrorSanitizer);
    }

    private function mockWindowResolver(bool $isWithinWindow = true): MaintenanceWindowResolver
    {
        return Mockery::mock(MaintenanceWindowResolver::class)
            ->shouldReceive('isWithinWindow')->andReturn($isWithinWindow)
            ->getMock();
    }

    private function noopMocks(): array
    {
        return [
            Mockery::mock(TsqlGeneratorService::class),
            Mockery::mock(MaintenanceWindowResolver::class),
            Mockery::mock(SqlServerConnectionFactory::class),
            Mockery::mock(SqlServerErrorSanitizer::class),
            Mockery::mock(WhatsAppService::class),
        ];
    }

    // ─────────────────────────────── early-return scenarios

    #[Test]
    public function test_returns_early_when_alert_not_found(): void
    {
        [$tsql, $window, $conn, $err, $wa] = $this->noopMocks();

        $job = new ExecuteMaintenanceJob(alertId: 999999);
        $job->handle($tsql, $window, $conn, $err, $wa);

        $this->assertDatabaseCount('maintenance_actions', 0);
    }

    #[Test]
    public function test_returns_early_when_alert_cannot_be_executed(): void
    {
        $alert = Alert::factory()->create([
            'server_id' => $this->server->id,
            'sql_index_id' => $this->index->id,
            'status' => AlertStatus::Pending,
        ]);

        [$tsql, $window, $conn, $err, $wa] = $this->noopMocks();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle($tsql, $window, $conn, $err, $wa);

        $this->assertDatabaseCount('maintenance_actions', 0);
    }

    #[Test]
    public function test_returns_early_when_action_already_running(): void
    {
        $alert = $this->createApprovedAlert();

        MaintenanceAction::create([
            'alert_id' => $alert->id,
            'server_id' => $this->server->id,
            'action_type' => 'REBUILD',
            'status' => MaintenanceStatus::Running,
            'sql_script' => 'SELECT 1',
            'started_at' => now(),
        ]);

        [$tsql, $window, $conn, $err, $wa] = $this->noopMocks();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle($tsql, $window, $conn, $err, $wa);

        $this->assertEquals(1, MaintenanceAction::count());
    }

    #[Test]
    public function test_returns_early_when_action_already_completed(): void
    {
        $alert = $this->createApprovedAlert();

        MaintenanceAction::create([
            'alert_id' => $alert->id,
            'server_id' => $this->server->id,
            'action_type' => 'REBUILD',
            'status' => MaintenanceStatus::Completed,
            'sql_script' => 'SELECT 1',
            'started_at' => now()->subMinute(),
            'executed_at' => now(),
        ]);

        [$tsql, $window, $conn, $err, $wa] = $this->noopMocks();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle($tsql, $window, $conn, $err, $wa);

        $this->assertEquals(1, MaintenanceAction::count());
    }

    #[Test]
    public function test_returns_early_when_server_inactive(): void
    {
        $inactiveServer = Server::factory()->create(['status' => 'inactive']);
        $alert = $this->createApprovedAlert(['server_id' => $inactiveServer->id]);

        [$tsql, $window, $conn, $err, $wa] = $this->noopMocks();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle($tsql, $window, $conn, $err, $wa);

        $alert->refresh();
        $this->assertEquals(AlertStatus::Failed, $alert->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'maintenance_result',
            'status' => 'failed',
        ]);
    }

    // ─────────────────────────────── successful execution

    #[Test]
    public function test_successful_execution_creates_action_and_updates_alert(): void
    {
        Bus::fake();

        $alert = $this->createApprovedAlert();
        $this->mockSuccessfulExecution();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle(
            $this->app->make(TsqlGeneratorService::class),
            $this->mockWindowResolver(),
            $this->app->make(SqlServerConnectionFactory::class),
            $this->app->make(SqlServerErrorSanitizer::class),
            $this->app->make(WhatsAppService::class),
        );

        $alert->refresh();
        $this->assertEquals(AlertStatus::Succeeded, $alert->status);

        $action = MaintenanceAction::where('alert_id', $alert->id)->first();
        $this->assertNotNull($action);
        $this->assertEquals(MaintenanceStatus::Completed, $action->status);
        $this->assertNotNull($action->sql_script);
        $this->assertNotNull($action->executed_at);
        $this->assertNotNull($action->duration_seconds);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'maintenance_execute',
            'status' => 'started',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'maintenance_result',
            'status' => 'succeeded',
        ]);
    }

    #[Test]
    public function test_successful_execution_dispatches_scan_job(): void
    {
        $alert = $this->createApprovedAlert();
        $this->mockSuccessfulExecution();
        Bus::fake();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle(
            $this->app->make(TsqlGeneratorService::class),
            $this->mockWindowResolver(),
            $this->app->make(SqlServerConnectionFactory::class),
            $this->app->make(SqlServerErrorSanitizer::class),
            $this->app->make(WhatsAppService::class),
        );

        Bus::assertDispatched(ScanServerJob::class);
    }

    #[Test]
    public function test_successful_execution_skips_whatsapp_when_no_contact(): void
    {
        Bus::fake();

        $alert = $this->createApprovedAlert();

        $mockWhatsapp = Mockery::mock(WhatsAppService::class);
        $mockWhatsapp->shouldNotReceive('sendConfirmation');

        $mockConnection = Mockery::mock(Connection::class);
        $mockConnection->shouldReceive('statement')->once();

        $mockFactory = Mockery::mock(SqlServerConnectionFactory::class);
        $mockFactory->shouldReceive('connect')->once()->andReturn($mockConnection);
        $mockFactory->shouldReceive('disconnect')->once();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle(
            $this->app->make(TsqlGeneratorService::class),
            $this->mockWindowResolver(),
            $mockFactory,
            $this->app->make(SqlServerErrorSanitizer::class),
            $mockWhatsapp,
        );
    }

    // ─────────────────────────────── failed execution

    #[Test]
    public function test_failed_execution_marks_action_and_alert_as_failed(): void
    {
        Bus::fake();

        $alert = $this->createApprovedAlert();
        $this->mockFailingExecution('Deadlock detected');

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle(
            $this->app->make(TsqlGeneratorService::class),
            $this->mockWindowResolver(),
            $this->app->make(SqlServerConnectionFactory::class),
            $this->app->make(SqlServerErrorSanitizer::class),
            $this->app->make(WhatsAppService::class),
        );

        $alert->refresh();
        $this->assertEquals(AlertStatus::Failed, $alert->status);

        $action = MaintenanceAction::where('alert_id', $alert->id)->first();
        $this->assertNotNull($action);
        $this->assertEquals(MaintenanceStatus::Failed, $action->status);
        $this->assertNotNull($action->error_message);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'maintenance_result',
            'status' => 'failed',
        ]);
    }

    #[Test]
    public function test_failed_execution_skips_whatsapp_when_no_contact(): void
    {
        Bus::fake();

        $alert = $this->createApprovedAlert();

        $mockWhatsapp = Mockery::mock(WhatsAppService::class);
        $mockWhatsapp->shouldNotReceive('sendConfirmation');

        $mockConnection = Mockery::mock(Connection::class);
        $mockConnection->shouldReceive('statement')->once()->andThrow(
            new \RuntimeException('Deadlock detected'),
        );

        $mockFactory = Mockery::mock(SqlServerConnectionFactory::class);
        $mockFactory->shouldReceive('connect')->once()->andReturn($mockConnection);
        $mockFactory->shouldReceive('disconnect')->once();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle(
            $this->app->make(TsqlGeneratorService::class),
            $this->mockWindowResolver(),
            $mockFactory,
            $this->app->make(SqlServerErrorSanitizer::class),
            $mockWhatsapp,
        );
    }

    // ─────────────────────────────── maintenance window

    #[Test]
    public function test_requeues_when_outside_maintenance_window(): void
    {
        Bus::fake();

        $alert = $this->createApprovedAlert([
            'scheduled_for' => now()->addHours(2),
        ]);

        [$tsql, $conn, $err, $wa] = [
            Mockery::mock(TsqlGeneratorService::class),
            Mockery::mock(SqlServerConnectionFactory::class),
            Mockery::mock(SqlServerErrorSanitizer::class),
            Mockery::mock(WhatsAppService::class),
        ];

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle($tsql, $this->mockWindowResolver(false), $conn, $err, $wa);

        Bus::assertDispatched(ExecuteMaintenanceJob::class, function ($job) {
            return $job->attempt === 1;
        });
    }

    #[Test]
    public function test_fails_when_outside_window_and_max_attempts_reached(): void
    {
        Bus::fake();

        $alert = $this->createApprovedAlert([
            'scheduled_for' => now()->addHours(2),
        ]);

        $job = new ExecuteMaintenanceJob(alertId: $alert->id, attempt: 3);
        $job->handle(
            Mockery::mock(TsqlGeneratorService::class),
            $this->mockWindowResolver(false),
            Mockery::mock(SqlServerConnectionFactory::class),
            Mockery::mock(SqlServerErrorSanitizer::class),
            Mockery::mock(WhatsAppService::class),
        );

        $alert->refresh();
        $this->assertEquals(AlertStatus::Failed, $alert->status);
    }

    // ─────────────────────────────── SQL

    #[Test]
    public function test_sql_script_is_saved_to_maintenance_action(): void
    {
        Bus::fake();

        $alert = $this->createApprovedAlert();
        $this->mockSuccessfulExecution();

        $job = new ExecuteMaintenanceJob(alertId: $alert->id);
        $job->handle(
            $this->app->make(TsqlGeneratorService::class),
            $this->mockWindowResolver(),
            $this->app->make(SqlServerConnectionFactory::class),
            $this->app->make(SqlServerErrorSanitizer::class),
            $this->app->make(WhatsAppService::class),
        );

        $action = MaintenanceAction::where('alert_id', $alert->id)->first();
        $this->assertStringContainsString('ALTER INDEX', $action->sql_script);
        $this->assertStringContainsString('IX_Users_Email', $action->sql_script);
        $this->assertStringContainsString('REBUILD', $action->sql_script);
    }
}
