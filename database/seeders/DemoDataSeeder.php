<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\AuthorizedContact;
use App\Models\GeneratedReport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Update existing user with admin role
        User::where('email', 'test@example.com')->update([
            'role' => 'admin',
            'password' => Hash::make('admin123')
        ]);
        $admin = User::where('email', 'test@example.com')->first();

        // Create additional users
        User::updateOrCreate(
            ['email' => 'operator@indexwatch.test'],
            ['name' => 'Operator', 'password' => Hash::make('admin123'), 'role' => 'operator']
        );
        User::updateOrCreate(
            ['email' => 'viewer@indexwatch.test'],
            ['name' => 'Viewer', 'password' => Hash::make('admin123'), 'role' => 'viewer']
        );

        // Create server
        $server = Server::updateOrCreate(
            ['name' => 'SQL Server Demo'],
            [
                'host' => '127.0.0.1', 'port' => 1433, 'database_name' => 'IndexWatch_Test',
                'username' => 'indexwatch_scanner', 'password' => 'test_password_123',
                'status' => 'active', 'warning_threshold' => 5.00, 'critical_threshold' => 30.00,
                'stats_stale_threshold' => 20.00, 'minimum_index_pages' => 1000, 'timezone' => 'America/Lima',
                'health_score' => 72, 'health_score_details' => json_encode(['critical_indexes' => 2, 'stale_statistics' => 1]),
            ]
        );

        // Delete old indexes and create new ones
        SqlIndex::where('server_id', $server->id)->delete();
        $tables = ['Pedidos', 'Clientes', 'Facturas', 'Productos', 'Inventario', 'Empleados', 'Categorias', 'Ventas', 'Logistica', 'Contabilidad'];
        $idxId = 0;
        foreach ($tables as $i => $table) {
            $frag = rand(0, 65);
            for ($j = 0; $j < rand(1, 4); $j++) {
                $idxId++;
                $isPk = ($j === 0);
                SqlIndex::create([
                    'server_id' => $server->id, 'schema_name' => 'dbo', 'table_name' => $table,
                    'index_name' => ($isPk ? 'PK_' : 'IX_') . $table . '_' . chr(97 + $j),
                    'object_id' => 1000 + $i * 10 + $j, 'index_id_native' => $j + 1,
                    'type' => $isPk ? 'CLUSTERED' : 'NONCLUSTERED',
                    'is_unique' => $isPk, 'is_primary_key' => $isPk, 'is_disabled' => false,
                    'fragmentation_percent' => $frag + rand(0, 20),
                    'size_mb' => rand(10, 5000), 'page_count' => rand(100, 50000),
                    'fill_factor' => [80, 90, 100][rand(0, 2)],
                    'user_seeks' => $isPk ? rand(100000, 500000) : rand(0, 500000),
                    'user_scans' => rand(0, 100000), 'user_lookups' => rand(0, 50000),
                    'user_updates' => rand(0, 200000),
                    'last_checked_at' => now()->subMinutes(rand(1, 120)),
                    'usage_stats_since' => now()->subDays(rand(5, 60)),
                ]);
            }
        }

        // Delete old alerts and create new ones
        Alert::where('server_id', $server->id)->delete();
        $realIndexIds = SqlIndex::where('server_id', $server->id)->pluck('id')->toArray();
        $realIndexIds = count($realIndexIds) > 0 ? $realIndexIds : [1];

        $alertsData = [
            ['sql_index_id' => $realIndexIds[0] ?? null, 'alert_type' => 'fragmentation','severity' => 'critical','status' => 'pending','recommended_action' => 'REBUILD','fragmentation_percent' => 62.5],
            ['sql_index_id' => $realIndexIds[min(2, count($realIndexIds)-1)] ?? null, 'alert_type' => 'fragmentation','severity' => 'warning','status' => 'pending','recommended_action' => 'REORGANIZE','fragmentation_percent' => 22.3],
            ['sql_index_id' => null, 'alert_type' => 'stale_statistics','severity' => 'warning','status' => 'pending','recommended_action' => 'UPDATE STATISTICS'],
            ['sql_index_id' => null, 'alert_type' => 'missing_index','severity' => 'info','status' => 'pending','recommended_action' => 'REVIEW'],
            ['sql_index_id' => $realIndexIds[min(6, count($realIndexIds)-1)] ?? null, 'alert_type' => 'fragmentation','severity' => 'critical','status' => 'pending','recommended_action' => 'REBUILD','fragmentation_percent' => 55.8],
            ['sql_index_id' => $realIndexIds[min(1, count($realIndexIds)-1)] ?? null, 'alert_type' => 'fill_factor','severity' => 'info','status' => 'pending','recommended_action' => 'REVIEW'],
            ['sql_index_id' => null, 'alert_type' => 'inactive','severity' => 'warning','status' => 'pending','recommended_action' => 'REVIEW'],
            ['sql_index_id' => $realIndexIds[min(count($realIndexIds)-1, count($realIndexIds)-1)] ?? null, 'alert_type' => 'fragmentation','severity' => 'warning','status' => 'approved','recommended_action' => 'REORGANIZE','fragmentation_percent' => 18.4],
            ['sql_index_id' => null, 'alert_type' => 'heap','severity' => 'info','status' => 'dismissed','recommended_action' => 'REVIEW'],
            ['sql_index_id' => $realIndexIds[min(3, count($realIndexIds)-1)] ?? null, 'alert_type' => 'fragmentation','severity' => 'critical','status' => 'succeeded','recommended_action' => 'REBUILD','fragmentation_percent' => 45.0],
        ];

        $tableNames = ['Pedidos','Clientes','Facturas','Productos','Inventario','Empleados','Categorias','Ventas','Logistica','Contabilidad'];
        foreach ($alertsData as $alertData) {
            $idx = $alertData['sql_index_id'] ?? 0;
            $tName = $idx > 0 ? $tableNames[($idx - 1) % count($tableNames)] : 'Productos';
            $alertData['fingerprint'] = hash('sha256', $server->id . ':' . $alertData['alert_type'] . ':' . ($idx > 0 ? $idx : '0'));
            $alertData['metadata'] = [
                'index_name' => $idx > 0 ? ('IX_' . $tName . '_col') : null,
                'table_name' => $tName,
                'page_count' => rand(5000, 40000),
                'size_mb' => rand(100, 3000),
                'modification_percent' => $alertData['alert_type'] === 'stale_statistics' ? 45.2 : null,
                'stats_name' => $alertData['alert_type'] === 'stale_statistics' ? '_WA_Sys_00000003' : null,
                'equality_columns' => $alertData['alert_type'] === 'missing_index' ? ['departamento_id', 'activo'] : null,
                'estimated_impact' => $alertData['alert_type'] === 'missing_index' ? 2500 : null,
            ];
            $alertData['server_id'] = $server->id;
            Alert::create($alertData);
        }

        // Audit logs
        AuditLog::where('server_id', $server->id)->delete();
        $actions = ['approved', 'executed', 'failed', 'scheduled', 'scanned', 'created', 'cancelled'];
        $sources = ['webhook', 'job', 'dashboard', 'scheduler', 'cli'];
        $actors = ['system', 'whatsapp', 'user', 'api'];
        for ($i = 0; $i < 12; $i++) {
            AuditLog::create([
                'server_id' => $server->id, 'auditable_type' => Alert::class,
                'auditable_id' => rand(1, count($alertsData)),
                'actor_type' => $actors[rand(0, 3)], 'source' => $sources[rand(0, 4)],
                'action' => $actions[rand(0, 6)], 'actor_name' => 'Seeder',
                'description' => 'Test audit entry #' . ($i + 1),
                'created_at' => now()->subHours(rand(0, 72)),
            ]);
        }

        // Maintenance windows
        $server->maintenanceWindows()->delete();
        for ($d = 1; $d <= 5; $d++) {
            $server->maintenanceWindows()->create([
                'day_of_week' => $d, 'start_time' => sprintf('%02d:00', rand(20, 22)),
                'end_time' => sprintf('%02d:00', 23), 'active' => true,
            ]);
        }

        // Authorized contacts
        AuthorizedContact::updateOrCreate(
            ['phone_e164' => '+51999999999'],
            ['name' => 'DBA Principal', 'role' => 'operator', 'active' => true]
        );
        AuthorizedContact::updateOrCreate(
            ['phone_e164' => '+51988888888'],
            ['name' => 'Admin WhatsApp', 'role' => 'admin', 'active' => true]
        );

        // Generated report
        GeneratedReport::updateOrCreate(
            ['server_id' => $server->id, 'requested_by_user_id' => $admin->id],
            [
                'filters' => ['date_from' => now()->subDays(30)->format('Y-m-d'), 'date_to' => now()->format('Y-m-d')],
                'format' => 'html', 'status' => 'completed',
                'file_path' => 'private/reports/demo_report.html', 'expires_at' => now()->addDays(7),
            ]
        );

        echo "\n=== DEMO DATA READY ===\n";
        echo "Login: test@example.com / admin123 (admin)\n";
        echo "Also: operator@indexwatch.test / viewer@indexwatch.test (admin123)\n";
        echo "Server: {$server->name} (ID: {$server->id})\n";
        echo "Indexes: " . SqlIndex::count() . "\n";
        echo "Alerts: " . Alert::count() . "\n";
        echo "Audit: " . AuditLog::count() . "\n";
        echo "Windows: " . $server->maintenanceWindows()->count() . "\n";
        echo "Contacts: " . AuthorizedContact::count() . "\n";
    }
}