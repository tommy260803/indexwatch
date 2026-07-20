<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' SQL Server',
            'host' => fake()->ipv4(),
            'port' => 1433,
            'database_name' => fake()->word() . '_db',
            'username' => 'scanner',
            'password' => fake()->password(16),
            'status' => 'active',
            'connection_options' => [
                'encrypt' => true,
                'trust_server_certificate' => true,
                'timeout' => 60,
            ],
            'warning_threshold' => 5.00,
            'critical_threshold' => 30.00,
            'stats_stale_threshold' => 20.00,
            'minimum_index_pages' => 1000,
            'timezone' => 'America/Lima',
        ];
    }
}