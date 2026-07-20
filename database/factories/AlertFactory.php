<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Server;
use App\Models\SqlIndex;
use App\Models\StatisticsStatus;
use App\Enums\AlertType;
use App\Enums\AlertStatus;
use App\Enums\AlertSeverity;
use App\Enums\RecommendedAction;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            AlertType::Fragmentation,
            AlertType::Inactive,
            AlertType::MissingIndex,
            AlertType::DuplicateIndex,
            AlertType::Heap,
            AlertType::StaleStatistics,
            AlertType::PageSplits,
            AlertType::FillFactor,
        ]);

        $severity = fake()->randomElement([AlertSeverity::Info, AlertSeverity::Warning, AlertSeverity::Critical]);

        $action = match ($type) {
            AlertType::Fragmentation => $severity === AlertSeverity::Critical ? RecommendedAction::Rebuild : RecommendedAction::Reorganize,
            AlertType::FillFactor => RecommendedAction::Review,
            AlertType::PageSplits => RecommendedAction::Review,
            AlertType::StaleStatistics => RecommendedAction::UpdateStatistics,
            AlertType::MissingIndex => RecommendedAction::Review,
            AlertType::Inactive => RecommendedAction::Review,
            AlertType::DuplicateIndex => RecommendedAction::Review,
            AlertType::Heap => RecommendedAction::Review,
            default => RecommendedAction::Review,
        };

        return [
            'server_id' => Server::factory(),
            'sql_index_id' => null,
            'subject_type' => SqlIndex::class,
            'subject_id' => null,
            'fingerprint' => 'test_' . fake()->uuid(),
            'alert_type' => $type,
            'severity' => $severity,
            'status' => AlertStatus::Pending,
            'recommended_action' => $action,
            'fragmentation_percent' => $type === AlertType::Fragmentation ? fake()->randomFloat(2, 5, 80) : null,
            'metadata' => [
                'size_mb' => fake()->randomFloat(2, 1, 5000),
                'page_count' => fake()->numberBetween(100, 50000),
            ],
            'whatsapp_message_id' => null,
        ];
    }

    public function forFragmentation(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => AlertType::Fragmentation,
            'fragmentation_percent' => fake()->randomFloat(2, 30, 80),
            'recommended_action' => fake()->boolean() ? RecommendedAction::Rebuild : RecommendedAction::Reorganize,
        ]);
    }

    public function forMissingIndex(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => AlertType::MissingIndex,
            'recommended_action' => RecommendedAction::Review,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::Pending,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}