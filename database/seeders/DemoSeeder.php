<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\SqlIndex;
use App\Models\Server;
use App\Enums\AlertType;
use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\IndexRecordStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $server = Server::create([
            'name'          => 'Servidor Producción - ERP',
            'host'          => '192.168.1.10',
            'database_name' => 'ERP_Produccion',
            'username'      => 'sa',
            'password'      => 'demo123',
            'status'        => 'active',
        ]);

        // Índice crítico por fragmentación
        $idx1 = SqlIndex::create([
            'server_id'             => $server->id,
            'table_name'            => 'Ventas',
            'index_name'            => 'IX_Ventas_ProductoID',
            'object_id'             => 1001,
            'index_id_native'       => 2,
            'fragmentation_percent' => 85.40,
            'size_mb'               => 250,
            'user_seeks'            => 12430,
            'user_scans'            => 890,
            'user_lookups'          => 340,
            'last_user_seek_at'     => now()->subMinutes(5),
            'status'                => IndexRecordStatus::Active,
        ]);

        Alert::create([
            'server_id'    => $server->id,
            'sql_index_id' => $idx1->id,
            'subject_type' => SqlIndex::class,
            'subject_id' => $idx1->id,
            'fingerprint' => Alert::makeFingerprint($server->id, AlertType::Fragmentation, $idx1->id, SqlIndex::class, $idx1->id),
            'alert_type'   => AlertType::Fragmentation,
            'severity'     => AlertSeverity::Critical,
            'status'       => AlertStatus::Pending,
            'fragmentation_percent' => 85.40,
        ]);

        // Índice inactivo candidato a DROP
        $idx2 = SqlIndex::create([
            'server_id'             => $server->id,
            'table_name'            => 'Clientes',
            'index_name'            => 'IX_Clientes_CodigoPostal_Antiguo',
            'object_id'             => 1002,
            'index_id_native'       => 3,
            'fragmentation_percent' => 12.00,
            'size_mb'               => 45,
            'user_seeks'            => 0,
            'user_scans'            => 0,
            'user_lookups'          => 0,
            'last_user_seek_at'     => now()->subDays(60),
            'status'                => IndexRecordStatus::Active,
        ]);

        Alert::create([
            'server_id'    => $server->id,
            'sql_index_id' => $idx2->id,
            'subject_type' => SqlIndex::class,
            'subject_id' => $idx2->id,
            'fingerprint' => Alert::makeFingerprint($server->id, AlertType::Inactive, $idx2->id, SqlIndex::class, $idx2->id),
            'alert_type'   => AlertType::Inactive,
            'severity'     => AlertSeverity::Warning,
            'status'       => AlertStatus::Pending,
        ]);
    }
}
