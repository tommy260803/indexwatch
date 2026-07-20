<?php

namespace Database\Factories;

use App\Models\SqlIndex;
use App\Models\Server;
use App\Enums\IndexType;
use App\Enums\IndexRecordStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class SqlIndexFactory extends Factory
{
    protected $model = SqlIndex::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'schema_name' => 'dbo',
            'table_name' => fake()->word() . '_' . fake()->word(),
            'index_name' => 'IX_' . fake()->word() . '_' . fake()->word(),
            'object_id' => fake()->numberBetween(1000, 999999),
            'index_id_native' => fake()->numberBetween(1, 50),
            'type' => fake()->randomElement([IndexType::Clustered, IndexType::Nonclustered]),
            'is_unique' => false,
            'is_primary_key' => false,
            'is_disabled' => false,
            'status' => IndexRecordStatus::Active,
            'fragmentation_percent' => fake()->randomFloat(2, 0, 80),
            'size_mb' => fake()->randomFloat(2, 1, 5000),
            'page_count' => fake()->numberBetween(100, 50000),
            'fill_factor' => fake()->randomElement([80, 90, 100]),
            'user_seeks' => fake()->numberBetween(0, 100000),
            'user_scans' => fake()->numberBetween(0, 50000),
            'user_lookups' => fake()->numberBetween(0, 10000),
            'user_updates' => fake()->numberBetween(0, 20000),
            'last_user_seek_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'last_user_scan_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'last_user_lookup_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'usage_stats_since' => fake()->dateTimeBetween('-60 days', '-1 day'),
            'last_checked_at' => now(),
        ];
    }
}