<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Index;
use App\Models\Server;
use Illuminate\Database\Seeder;

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
            'active'        => true,
        ]);

        // Índice crítico por fragmentación
        $idx1 = Index::create([
            'server_id'             => $server->id,
            'table_name'            => 'Ventas',
            'index_name'            => 'IX_Ventas_ProductoID',
            'fragmentation_percent' => 85.40,
            'size_mb'               => 250,
            'seeks'                 => 12430,
            'scans'                 => 890,
            'lookups'               => 340,
            'last_used_at'          => now()->subMinutes(5),
        ]);

        Alert::create([
            'index_id' => $idx1->id,
            'type'     => 'fragmentation',
            'severity' => 'critical',
            'status'   => 'pending',
        ]);

        // Índice inactivo candidato a DROP
        $idx2 = Index::create([
            'server_id'             => $server->id,
            'table_name'            => 'Clientes',
            'index_name'            => 'IX_Clientes_CodigoPostal_Antiguo',
            'fragmentation_percent' => 12.00,
            'size_mb'               => 45,
            'seeks'                 => 0,
            'scans'                 => 0,
            'lookups'               => 0,
            'last_used_at'          => now()->subDays(60),
        ]);

        Alert::create([
            'index_id' => $idx2->id,
            'type'     => 'inactive',
            'severity' => 'warning',
            'status'   => 'pending',
        ]);
    }
}